<?php

namespace App\DataFixtures;

/**
 * Clés de référence partagées entre les classes de fixtures.
 *
 * Chaque clé est préfixée par la COMPAGNIE : le jeu de données est multi-entreprises et deux
 * compagnies peuvent parfaitement avoir une gare « Abidjan » ou un rôle « Guichetier ». Sans ce
 * préfixe, la seconde écraserait silencieusement la référence de la première.
 */
final class Refs
{
    /** Compagnie principale : jeu de données complet (exploitation, billetterie, stock, flotte). */
    public const IRA = 'ira';

    /** Seconde compagnie, volontairement minimale : elle sert à VÉRIFIER l'isolation du périmètre entreprise. */
    public const SAHEL = 'sahel';

    /** Mot de passe commun à tous les comptes du jeu de données (environnement de dev uniquement). */
    public const MOTDEPASSE = 'aaaa';

    public static function entreprise(string $cie): string
    {
        return "entreprise-$cie";
    }

    public static function ville(string $cie, string $code): string
    {
        return "ville-$cie-$code";
    }

    public static function gare(string $cie, string $code): string
    {
        return "gare-$cie-$code";
    }

    public static function user(string $cie, string $code): string
    {
        return "user-$cie-$code";
    }

    public static function role(string $cie, string $code): string
    {
        return "role-$cie-$code";
    }

    public static function ligne(string $cie, string $code): string
    {
        return "ligne-$cie-$code";
    }

    public static function tarif(string $cie, string $depart, string $arrivee): string
    {
        return "tarif-$cie-$depart-$arrivee";
    }

    public static function car(string $cie, string $code): string
    {
        return "car-$cie-$code";
    }

    /** Un siège est identifié par son car ET son numéro : c'est ce couple que vise la billetterie. */
    public static function siege(string $cie, string $car, int $numero): string
    {
        return "siege-$cie-$car-$numero";
    }

    public static function voyage(string $cie, string $code): string
    {
        return "voyage-$cie-$code";
    }

    public static function personnel(string $cie, string $code): string
    {
        return "personnel-$cie-$code";
    }

    public static function client(string $cie, string $code): string
    {
        return "client-$cie-$code";
    }

    public static function beneficiaire(string $cie, string $code): string
    {
        return "beneficiaire-$cie-$code";
    }

    public static function ticket(string $cie, string $code): string
    {
        return "ticket-$cie-$code";
    }

    public static function reservation(string $cie, string $code): string
    {
        return "reservation-$cie-$code";
    }

    public static function piece(string $cie, string $code): string
    {
        return "piece-$cie-$code";
    }

    public static function fournisseur(string $cie, string $code): string
    {
        return "fournisseur-$cie-$code";
    }

    public static function tarifbagage(string $cie, string $code): string
    {
        return "tarifbagage-$cie-$code";
    }

    public static function tarifcourrier(string $cie, string $code): string
    {
        return "tarifcourrier-$cie-$code";
    }

    /** Référentiels simples (Marque, Typepiece, Typepanne, Typevehicule…) : « type-compagnie-code ». */
    public static function referentiel(string $type, string $cie, string $code): string
    {
        return "$type-$cie-$code";
    }
}
