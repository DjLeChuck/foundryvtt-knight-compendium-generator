<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MediaGrabber
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $knightMediaClient)
    {
        $this->client = $knightMediaClient;
    }

    public function grab(string $name, string $type, string $path): ?string
    {
        $response = $this->client->request('GET', $path);
        if (200 !== $response->getStatusCode()) {
            return null;
        }

        $filesystem = new Filesystem();
        $filesystem->dumpFile('var/files/'.$type.'/'.$name, $response->getContent());

        return 'var/files/'.$name;
    }
}
