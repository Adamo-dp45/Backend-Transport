<?php

namespace App\Entity\Interface;

interface HasSoftDeleteGuard
{
    /**
     * Va retourner une liste de messages d'erreur si la suppression est bloquée et '[]' si la suppression est autorisée
     * @return string[]
     */
    public function getSoftDeleteBlockers(): array;
}