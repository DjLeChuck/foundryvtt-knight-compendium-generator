<?php

declare(strict_types=1);

namespace App\Command;

use App\Api;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Serializer\SerializerInterface;

abstract class AbstractCompendiumCommand extends Command
{
    protected Api $api;
    protected SerializerInterface $serializer;

    public function __construct(Api $api, SerializerInterface $serializer)
    {
        $this->api = $api;
        $this->serializer = $serializer;

        parent::__construct();
    }

    protected function cleanDescription(string $value): string
    {
        $value = preg_replace('`\[(.*)]\([a-zA-Z0-9/-]*\)`', '$1', $value);

        return str_replace("\r\n", '<br />', $value);
    }

    protected function generateId($input): string
    {
        // Create a raw binary sha256 hash and base64 encode it.
        $hash_base64 = base64_encode(hash('sha256', $input, true));
        // Replace non-urlsafe chars to make the string urlsafe.
        $hashUrlsafe = strtr($hash_base64, '+/', '-_');
        // Trim base64 padding characters from the end.
        $hashUrlsafe = rtrim($hashUrlsafe, '=');

        // Shorten the string before returning.
        return substr($hashUrlsafe, 0, 16);
    }

    protected function getReach(?string $value): string
    {
        return match ($value) {
            null => 'personnelle',
            'Contact' => 'contact',
            'Courte' => 'courte',
            'Moyenne' => 'moyenne',
            'Longue' => 'longue',
            'Lointaine' => 'lointaine',
            default => throw new \InvalidArgumentException(sprintf('Portée "%s" invalide', $value)),
        };
    }
}
