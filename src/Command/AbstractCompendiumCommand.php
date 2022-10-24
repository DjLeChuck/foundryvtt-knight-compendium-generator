<?php

declare(strict_types=1);

namespace App\Command;

use App\Api;
use League\CommonMark\ConverterInterface;
use League\CommonMark\GithubFlavoredMarkdownConverter;
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
    protected ConverterInterface $converter;

    public function __construct(Api $api, SerializerInterface $serializer)
    {
        $this->api = $api;
        $this->serializer = $serializer;
        $this->converter = new GithubFlavoredMarkdownConverter();

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

    protected function dumpCompendium(array $dataset, bool $isMultiple = true): void
    {
        $filesystem = new Filesystem();

        if (!$isMultiple) {
            $filesystem->dumpFile(
                sprintf('var/%s.db', $this->getPluralizedType()),
                implode(PHP_EOL, $dataset)
            );

            return;
        }

        foreach ($dataset as $pack => $items) {
            $filesystem->dumpFile(
                sprintf('var/%s-%s.db', $this->getPluralizedType(), $pack),
                implode(PHP_EOL, $items)
            );
        }
    }

    protected function getBaseData(): array
    {
        return json_decode(
            file_get_contents(sprintf('var/data/%s_tpl.json', $this->getType())),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
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
            return sprintf('modules/knight-compendium/assets/%s/%s.png', $this->getPluralizedType(), $slug);
        }

        return null;
    }

    protected function cleanDescription(string $value): string
    {
        $value = $this->converter->convert($value)->getContent();

        return preg_replace('#<a\s.*?>(.*?)</a>#is', '<em>\1</em>', $value);
    }

    protected function generateId($input): string
    {
        return hash('crc32', $input).hash('crc32', strrev($input));
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

    protected function getWeaponType(string $value): string
    {
        return match ($value) {
            'Arme à distance' => 'distance',
            'Arme de contact' => 'contact',
            default => throw new \InvalidArgumentException(sprintf('Type "%s" invalide', $value)),
        };
    }

    protected function getRarity(?string $value): ?string
    {
        return match ($value) {
            null => null,
            'Standard' => 'standard',
            'Avancé' => 'avance',
            'Rare' => 'rare',
            'Prestige' => 'prestige',
            'Relique d\'espoir' => 'espoir',
            default => throw new \InvalidArgumentException(sprintf('Rareté "%s" invalide', $value)),
        };
    }
}
