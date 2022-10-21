<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MediaGrabber
{
    public const TYPE_ARMOUR = 'armours';
    public const TYPE_MODULE = 'modules';
    public const TYPE_WEAPON = 'weapons';

    private static array $paths = [
        self::TYPE_ARMOUR => 'armour_logo/anathema',
        self::TYPE_MODULE => 'module',
        self::TYPE_WEAPON => 'weapon',
    ];
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $knightMediaClient)
    {
        $this->client = $knightMediaClient;
    }

    public function grab(string $type, string $slug): void
    {
        $filename = sprintf('%s.png', $slug);
        $response = $this->client->request('GET', sprintf('%s/%s', self::$paths[$type], $filename));
        if (200 !== $response->getStatusCode()) {
            return;
        }

        $filesystem = new Filesystem();
        $filesystem->dumpFile(sprintf('var/files/%s/%s', $type, $filename), $response->getContent());
    }
}
