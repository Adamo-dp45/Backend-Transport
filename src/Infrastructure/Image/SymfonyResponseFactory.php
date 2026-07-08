<?php

namespace App\Infrastructure\Image;

use League\Flysystem\FilesystemOperator;
use League\Glide\Responses\ResponseFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SymfonyResponseFactory implements ResponseFactoryInterface
{
    public function __construct(protected ?Request $request = null)
    {
    }

    /**
     * Permet de créer une response
     *
     * @param FilesystemOperator $cache the cache file system
     * @param string             $path  the cached file path
     */
    public function create(FilesystemOperator $cache, string $path): Response
    {
        $content = $cache->read($path); // Va lire le contenu de l'image depuis le cache
        $response = new Response($content);
        $response->headers->set('Content-Type', $cache->mimeType($path) ?? 'image/jpeg');
        $response->headers->set('Content-Length', (string) $cache->fileSize($path));
        $response->setPublic();
        $response->setMaxAge(31_536_000);
        $response->setExpires(new \DateTimeImmutable('+1 year'));

        if($this->request) {
            $lastModified = new \DateTimeImmutable('@' . $cache->lastModified($path));
            $response->setLastModified($lastModified);
            $response->isNotModified($this->request);
        }
        return $response;
    }
}