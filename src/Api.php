<?php

declare(strict_types=1);

namespace App;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Api
{
    private HttpClientInterface $client;
    private CacheInterface $cache;

    public function __construct(HttpClientInterface $knightApiClient, CacheInterface $cache)
    {
        $this->client = $knightApiClient;
        $this->cache = $cache;
    }

    public function get(string $path): array
    {
        return $this->cache->get($this->getKey($path), function (ItemInterface $item) use ($path) {
            $item->expiresAfter(3600 * 6);

            return $this->client->request('GET', $path)->toArray();
        });
    }

    private function getKey(string $path): string
    {
        return base64_encode($path);
    }
}
