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
use Symfony\Component\Routing\Attribute\Route;

final class ImageController extends AbstractController
{
    #[Route('/media/{path}', name: 'glide', methods: ['GET'], requirements: ['path' => '.+'])]
    public function glide(
        Request $request,
        string $path,
        ParameterBagInterface $params
    )
    {
        $server = ServerFactory::create([
            'response' => new SymfonyResponseFactory(),
            'source' => $params->get('kernel.project_dir') . '/public',
            'cache' => $params->get('kernel.project_dir') . '/public/images/cache',
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
