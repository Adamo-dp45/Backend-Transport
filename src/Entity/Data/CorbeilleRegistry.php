<?php

namespace App\Entity\Data;

use App\Entity\Approvisionnement;
use App\Entity\Bagage;
use App\Entity\Car;
use App\Entity\Courrier;
use App\Entity\Depannage;
use App\Entity\Fournisseur;
use App\Entity\Gare;
use App\Entity\Ligne;
use App\Entity\Marque;
use App\Entity\Marquepiece;
use App\Entity\Model;
use App\Entity\Modelvehicule;
use App\Entity\Personnel;
use App\Entity\Piece;
use App\Entity\Role;
use App\Entity\Tarif;
use App\Entity\Tarifbagage;
use App\Entity\Tarifcourrier;
use App\Entity\Ticket;
use App\Entity\Typepersonnel;
use App\Entity\Typepiece;
use App\Entity\Typevehicule;
use App\Entity\Voyage;

class CorbeilleRegistry
{
    // Mapping type => classe entité
    private const MAP = [
        'approvisionnement' => Approvisionnement::class,
        'bagage' => Bagage::class,
        'car' => Car::class,
        'courrier' => Courrier::class,
        'depannage' => Depannage::class,
        'fournisseur' => Fournisseur::class,
        'gare' => Gare::class,
        'ligne' => Ligne::class,
        'marque' => Marque::class,
        'marquepiece' => Marquepiece::class,
        'model' => Model::class,
        'modelvehicule' => Modelvehicule::class,
        'personnel' => Personnel::class,
        'piece' => Piece::class,
        'role' => Role::class,
        'tarifbagage' => Tarifbagage::class,
        'tarifcourrier' => Tarifcourrier::class,
        'tarif' => Tarif::class,
        'ticket' => Ticket::class,
        'typepersonnel' => Typepersonnel::class,
        'typepiece' => Typepiece::class,
        'typevehicule' => Typevehicule::class,
        'voyage' => Voyage::class
    ];

    public function getClass(string $type): ?string
    {
        return self::MAP[strtolower($type)] ?? null;
    }

    public function getTypes(): array
    {
        return array_keys(self::MAP);
    }

    public function getMap(): array
    {
        return self::MAP;
    }
}