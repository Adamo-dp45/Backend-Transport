<?php

namespace App\Domain\Service;

use App\Entity\Client;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Rattache une transaction (ticket, courrier) à une identité Client durable, par TÉLÉPHONE.
 *
 * Logique « find-or-create » : on cherche un client actif de l'entreprise ayant ce numéro ;
 * s'il n'existe pas, on le crée. Le rattachement est NON-CASSANT — les champs texte libre de la
 * transaction (nomclient/contactclient…) restent le snapshot ; ce client vient les enrichir.
 *
 * Sans téléphone, on ne peut pas dédupliquer : on renvoie null (la transaction garde son snapshot).
 */
class ClientResolver
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClientRepository $clientRepository
    )
    {
    }

    /**
     * @return Client|null Le client (existant ou créé), ou null si aucun téléphone exploitable.
     * @param int|null $userId auteur du rattachement (null pour un invité mobile/web sans compte)
     */
    public function resolve(?string $nom, ?string $contact, int $identreprise, ?int $userId = null): ?Client
    {
        $contact = $this->normaliserContact($contact);
        if ($contact === null) {
            return null;
        }

        $nom = $nom !== null ? trim($nom) : '';

        $client = $this->clientRepository->findOneActifByContact($contact, $identreprise);
        if ($client !== null) {
            // Complète le nom si l'enregistrement existant n'en avait pas (téléphone seul à la création)
            if ($nom !== '' && ($client->getNom() === null || trim($client->getNom()) === '')) {
                $client->setNom($nom)->setUpdatedBy($userId);
            }
            return $client;
        }

        $client = new Client();
        $client
            ->setNom($nom !== '' ? $nom : $contact) // 'nom' est obligatoire : à défaut, le téléphone
            ->setContact($contact)
            ->setIdentreprise($identreprise)
            ->setCreatedBy($userId);

        $this->em->persist($client);
        $this->em->flush(); // besoin de l'id pour la FK de la transaction

        return $client;
    }

    /**
     * Normalise un téléphone pour servir de clé : on retire les espaces. Renvoie null si vide.
     */
    private function normaliserContact(?string $contact): ?string
    {
        if ($contact === null) {
            return null;
        }
        $contact = preg_replace('/\s+/', '', $contact);

        return $contact === '' ? null : $contact;
    }
}
