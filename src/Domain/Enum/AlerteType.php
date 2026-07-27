<?php

namespace App\Domain\Enum;

/**
 * Catalogue des types d'alertes. Chaque type porte sa SÉVÉRITÉ, sa PORTÉE (audience) et sa
 * FAMILLE par défaut — utilisés par le générateur à la création et pour le filtrage FT.
 * Extensible : ajouter un case, puis son mapping dans les trois match ci-dessous.
 */
enum AlerteType: string
{
    // Exploitation / voyages
    case VOYAGE_SANS_PERSONNEL     = 'VOYAGE_SANS_PERSONNEL';
    case VOYAGE_DEPART_EN_RETARD   = 'VOYAGE_DEPART_EN_RETARD';
    case PASSAGERS_EVINCES         = 'PASSAGERS_EVINCES';
    // Réservation & billetterie
    case BON_EXPIRE_BIENTOT        = 'BON_EXPIRE_BIENTOT';
    case NO_SHOW_A_REGULARISER     = 'NO_SHOW_A_REGULARISER';
    // Stock & flotte
    case STOCK_FAIBLE              = 'STOCK_FAIBLE';
    case STOCK_RUPTURE             = 'STOCK_RUPTURE';
    case DEPANNAGE_OUVERT_PROLONGE = 'DEPANNAGE_OUVERT_PROLONGE';
    // Anti-fraude & incidents
    case AGENT_ANNULATION_ELEVEE   = 'AGENT_ANNULATION_ELEVEE';
    case AGENT_REMISE_ELEVEE       = 'AGENT_REMISE_ELEVEE';
    case COURRIER_NON_LIVRE        = 'COURRIER_NON_LIVRE';
    case BAGAGE_NON_LIVRE          = 'BAGAGE_NON_LIVRE';

    public function severite(): AlerteSeverite
    {
        return match ($this) {
            self::VOYAGE_DEPART_EN_RETARD,
            self::STOCK_RUPTURE => AlerteSeverite::CRITIQUE,

            self::VOYAGE_SANS_PERSONNEL,
            self::PASSAGERS_EVINCES,
            self::BON_EXPIRE_BIENTOT,
            self::STOCK_FAIBLE,
            self::DEPANNAGE_OUVERT_PROLONGE,
            self::AGENT_ANNULATION_ELEVEE,
            self::AGENT_REMISE_ELEVEE => AlerteSeverite::AVERTISSEMENT,

            self::NO_SHOW_A_REGULARISER,
            self::COURRIER_NON_LIVRE,
            self::BAGAGE_NON_LIVRE => AlerteSeverite::INFO,
        };
    }

    public function portee(): AlertePortee
    {
        return match ($this) {
            self::VOYAGE_SANS_PERSONNEL,
            self::VOYAGE_DEPART_EN_RETARD,
            self::PASSAGERS_EVINCES,
            self::BON_EXPIRE_BIENTOT,
            self::NO_SHOW_A_REGULARISER,
            self::COURRIER_NON_LIVRE,
            self::BAGAGE_NON_LIVRE => AlertePortee::GARE,

            self::STOCK_FAIBLE,
            self::STOCK_RUPTURE,
            self::DEPANNAGE_OUVERT_PROLONGE => AlertePortee::ENTREPRISE,

            self::AGENT_ANNULATION_ELEVEE,
            self::AGENT_REMISE_ELEVEE => AlertePortee::DIRECTION,
        };
    }

    /** Famille métier (regroupement FT), alignée sur les 4 familles retenues. */
    public function famille(): string
    {
        return match ($this) {
            self::VOYAGE_SANS_PERSONNEL,
            self::VOYAGE_DEPART_EN_RETARD,
            self::PASSAGERS_EVINCES => 'EXPLOITATION',

            self::BON_EXPIRE_BIENTOT,
            self::NO_SHOW_A_REGULARISER => 'RESERVATION',

            self::STOCK_FAIBLE,
            self::STOCK_RUPTURE,
            self::DEPANNAGE_OUVERT_PROLONGE => 'STOCK_FLOTTE',

            self::AGENT_ANNULATION_ELEVEE,
            self::AGENT_REMISE_ELEVEE,
            self::COURRIER_NON_LIVRE,
            self::BAGAGE_NON_LIVRE => 'ANTIFRAUDE',
        };
    }
}
