<?php

namespace App\Infrastructure\Upload;

use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\NamerInterface;

/**
 * Un nom de fichier IMPOSSIBLE À DEVINER : 128 bits tirés de `random_bytes`, et l'extension.
 *
 * POURQUOI : les justificatifs (factures, bulletins de salaire) vivent sous `public/documents`, dont
 * l'accès est interdit par un `.htaccess`. Cette interdiction ne vaut que sous APACHE — le serveur de
 * développement (`symfony serve`) et Nginx ignorent le fichier et serviraient le document à qui en
 * connaît l'adresse. Le nom est la SECONDE barrière, celle qui ne dépend d'aucune configuration : il
 * n'est jamais exposé (le `contentUrl` d'un document vaut NULL), et il ne se DEVINE pas.
 *
 * Les namers de Vich ne conviennent pas : `UniqidNamer` et `SmartUniqueNamer` dérivent de `uniqid()`,
 * c'est-à-dire de l'HORLOGE — connaître l'heure approximative d'un dépôt réduit l'espace à explorer à
 * quelques millions de noms par seconde. Et `SmartUniqueNamer` garde le nom d'origine, qui porte souvent
 * celui du salarié.
 */
final class NomAleatoireNamer implements NamerInterface
{
    public function name(object|array $object, PropertyMapping $mapping): string
    {
        $fichier = $mapping->getFile($object);
        $extension = $fichier?->guessExtension() ?: 'bin'; // l'extension DÉDUITE du contenu, pas celle du client

        return sprintf('%s.%s', bin2hex(random_bytes(16)), $extension);
    }
}
