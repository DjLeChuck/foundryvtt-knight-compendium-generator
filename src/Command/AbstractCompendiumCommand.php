<?php

declare(strict_types=1);

namespace App\Command;

use Adbar\Dot;
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
    protected ?array $itemTemplate = null;
    protected ?array $customEffectTemplate = null;

    public function __construct(Api $api, SerializerInterface $serializer)
    {
        $this->api = $api;
        $this->serializer = $serializer;
        $this->converter = new GithubFlavoredMarkdownConverter();

        parent::__construct();
    }

    abstract protected function getType(): string;

    abstract protected function getEmptyObjectPaths(): array;

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

    protected function fixEmptyObjectPaths(array &$data): void
    {
        $dot = new Dot($data);

        foreach ($this->getEmptyObjectPaths() as $path) {
            if (!empty($dot->get($path))) {
                continue;
            }

            $dot->set($path, new \stdClass());
        }

        $data = $dot->all();
    }

    protected function serializeData(array $data): string
    {
        $this->fixEmptyObjectPaths($data);

        return $this->serializer->encode(
            $data,
            JsonEncoder::FORMAT,
            [JsonEncode::OPTIONS => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]
        );
    }

    protected function dumpCompendium(array $dataset): void
    {
        $filesystem = new Filesystem();

        foreach ($dataset as $pack => $items) {
            $filesystem->dumpFile(
                sprintf('var/packs/%s-%s.db', $this->getPluralizedType(), $pack),
                implode(PHP_EOL, $items)
            );
        }
    }

    protected function getTplName(): string
    {
        return sprintf('%s_tpl.json', $this->getType());
    }

    protected function getBaseData(): array
    {
        if (null === $this->itemTemplate) {
            $this->itemTemplate = json_decode(
                file_get_contents(sprintf('var/data/%s', $this->getTplName())),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }

        return $this->itemTemplate;
    }

    protected function getCustomEffectTemplate(): array
    {
        if (null === $this->customEffectTemplate) {
            $this->customEffectTemplate = json_decode(
                file_get_contents('var/data/custom_effect_tpl.json'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }

        return $this->customEffectTemplate;
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
            return sprintf('modules/knight-compendium/assets/%s/%s.webp', $this->getPluralizedType(), $slug);
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

    protected function getWeaponTypeFromReach(string $reach): string
    {
        return 'contact' === $reach ? 'contact' : 'distance';
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

    protected function ignoreEffect(string $value): bool
    {
        $value = str_replace(' X', '', $value);

        return str_starts_with($value, '[') && str_ends_with($value, ']');
    }

    protected function getEffect(string $value): string
    {
        return match ($value) {
            'anti-anatheme' => 'antianatheme',
            'anti-vehicule' => 'antivehicule',
            'artillerie' => 'artillerie',
            'assassin-x' => 'assassin',
            'assistance-a-lattaque' => 'assistanceattaque',
            'barrage-x' => 'barrage',
            'briser-la-resilience' => 'briserlaresilience',
            'cadence-x' => 'cadence',
            'chargeur-x' => 'chargeur',
            'choc-x' => 'choc',
            'defense-x' => 'defense',
            'degats-continus-x' => 'degatscontinus',
            'demoralisant' => 'demoralisant',
            'designation' => 'designation',
            'destructeur' => 'destructeur',
            'deux-mains' => 'deuxmains',
            'dispersion-x' => 'dispersion',
            'en-chaine' => 'enchaine',
            'esperance' => 'esperance',
            'fureur' => 'fureur',
            'ignore-armure' => 'ignorearmure',
            'ignore-cdf' => 'ignorechampdeforce',
            'jumele-akimbo' => 'jumeleakimbo',
            'jumele-ambidextrie' => 'jumeleambidextrie',
            'leste' => 'leste',
            'lourd' => 'lourd',
            'lumiere-x' => 'lumiere',
            'meurtrier' => 'meurtrier',
            'obliteration' => 'obliteration',
            'orfevrerie' => 'orfevrerie',
            'parasitage-x' => 'parasitage',
            'penetrant-x' => 'penetrant',
            'perce-armure-x' => 'percearmure',
            'precision' => 'precision',
            'reaction-x' => 'reaction',
            'silencieux' => 'silencieux',
            'soumission' => 'soumission',
            'tenebricide' => 'tenebricide',
            'tir-en-rafale' => 'tirenrafale',
            'tir-en-securite' => 'tirensecurite',
            'ultraviolence' => 'ultraviolence',
            default => throw new \InvalidArgumentException(sprintf('Effet "%s" invalide', $value)),
        };
    }
}
