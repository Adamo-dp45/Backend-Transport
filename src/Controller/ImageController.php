<?php

namespace App\Controller;

use App\Infrastructure\Image\SymfonyResponseFactory;
use League\Glide\ServerFactory;
use League\Glide\Signatures\SignatureException;
use League\Glide\Signatures\SignatureFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ImageController extends AbstractController
{
    /**
     * !! LES SEULS DOSSIERS QUE GLIDE LIT (29/09/2026). Sa source est tout `public/`, et les JUSTIFICATIFS
     * de dépense y vivent désormais (`public/documents`, interdit au serveur web par son `.htaccess`).
     * Sans cette liste, `/media/documents/<nom>.png` ferait lire le fichier PAR PHP — donc par-dessus
     * l'interdiction du `.htaccess`, qui ne s'applique qu'aux fichiers servis en statique. Le nom aléatoire
     * reste la seconde barrière ; cette liste ferme la porte que Glide ouvrait à côté.
     */
    private const DOSSIERS = '#^images/(media|users)/[^/]+$#';

    #[Route('/media/{path}', name: 'glide', methods: ['GET'], requirements: ['path' => '.+'])]
    public function glide(
        Request $request,
        string $path,
        ParameterBagInterface $params
    )
    {
        if(!preg_match(self::DOSSIERS, $path)) {
            throw new NotFoundHttpException();
        }

        $server = ServerFactory::create([
            'response' => new SymfonyResponseFactory(),
            'source' => $params->get('glide.source'), // 'public/' ; les fichiers de test en test
            'cache' => $params->get('glide.cache'),
            'base_url' => '/media',
            /*
                'presets'  => [
                    'avatar' => ['w' => 80,  'h' => 80,  'fit' => 'crop'],
                    'thumb' => ['w' => 150, 'h' => 150, 'fit' => 'crop'],
                    'medium' => ['w' => 400, 'h' => 400, 'fit' => 'contain']
                ] -- Permet d'appeler l'url '..?p=avatar' et on aura le format
            */
        ]);

        $url = $request->getPathInfo();
        try {
            // SignatureFactory::create($params->get('glide.key'))->validateRequest($url, $request->query->all());
            return $server->getImageResponse($path, $request->query->all());
        } catch (SignatureException) {
            throw new HttpException(403, 'Signature invalide');
        }
    }
}
