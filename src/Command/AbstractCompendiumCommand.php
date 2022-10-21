<?php

declare(strict_types=1);

namespace App\Command;

use App\Api;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\Inflector\EnglishInflector;

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

    abstract protected function getType(): string;

    protected function getPluralizedType(): string
    {
        $inflector = new EnglishInflector();

        return current($inflector->pluralize($this->getType()));
    }

    protected function getList(): array
    {
        return $this->api->get($this->getType());
    }

    protected function getItem(int $id): array
    {
        return $this->api->get(sprintf('%s/%u', $this->getType(), $id));
    }

    protected function serializeData(array $data): string
    {
        return $this->serializer->serialize(
            $data,
            JsonEncoder::FORMAT,
            [JsonEncode::OPTIONS => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]
        );
    }

    protected function dumpCompendium(array $dataset): void
    {
        $filesystem = new Filesystem();
        $filesystem->dumpFile(
            sprintf('var/%s.db', $this->getPluralizedType()),
            implode(PHP_EOL, $dataset)
        );
    }

    protected function getBaseData(): array
    {
        return include(sprintf('var/data/%s_tpl.php', $this->getType()));
    }

    protected function getImg(string $slug): ?string
    {
        static $existings = null;

        if (null === $existings) {
            $existings = [];
            $finder = new Finder();

            foreach ($finder->files()->in(sprintf('var/files/%s', $this->getPluralizedType())) as $file) {
                $existings[] = $file->getFilename();
            }
        }

        if (\in_array($slug.'.png', $existings, true)) {
            return sprintf('systems/knight/assets/%s/%s.png', $this->getPluralizedType(), $slug);
        }

        return null;
    }

    protected function cleanDescription(string $value): string
    {
        $value = preg_replace('`\[(.*)]\(.*\)`', '$1', $value);
        $value = preg_replace('`_(.*)_`', '<em>$1</em>', $value);

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
