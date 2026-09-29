<?php

namespace App\Controller\Api;

use App\Entity\Depense;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * Sert le fichier du justificatif d'une dépense.
 *
 * La dépense arrive DÉJÀ chargée et autorisée (`$data`) : c'est l'opération `Justificatif_Depense` qui
 * porte le voter et les extensions de périmètre. Ce contrôleur ne décide donc d'aucun droit — il ne
 * fait que trouver le fichier.
 */
#[AsController]
final class DepenseJustificatifController
{
    public function __construct(
        private readonly StorageInterface $storage
    )
    {
    }

    public function __invoke(Depense $data): BinaryFileResponse
    {
        $media = $data->getJustificatif();
        if($media === null || !$media->isPrive()) {
            throw new NotFoundHttpException('Cette dépense n\'a pas de justificatif.');
        }

        $chemin = $this->storage->resolvePath($media, 'document');
        if($chemin === null || !is_file($chemin)) {
            throw new NotFoundHttpException('Le fichier du justificatif est introuvable.'); /*
                - La ligne existe mais pas le fichier : stockage non sauvegardé, restauration partielle.
                  Un 404 qui le DIT plutôt qu'une 500 sur un 'fopen'
            */
        }

        $response = new BinaryFileResponse($chemin);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE, // un PDF ou une photo se CONSULTE dans le navigateur
            sprintf('justificatif-depense-%d.%s', $data->getId(), $response->getFile()->guessExtension() ?? 'bin')
        );
        /*
            - 'nosniff' : le navigateur s'en tient au type annoncé, il n'« interprète » pas un fichier
              téléversé comme du HTML
            - 'private, no-store' : une pièce comptable ne doit rester dans AUCUN cache partagé (proxy,
              CDN), ni sur le disque du poste une fois l'onglet fermé
        */
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
