<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

#[AsCommand(
    name: 'app:compendium:weapons',
    description: 'Génération du compendium des armes',
)]
class CompendiumWeaponsCommand extends AbstractCompendiumCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $items = [];

        foreach ($this->api->get('weapon') as $data) {
            $apiData = $this->api->get('weapon/'.$data['id']);
            $nbAttacks = \count($apiData['attacks']);
            $itemData = [
                'name'   => $apiData['name'],
                'type'   => 'arme',
                'img'    => $this->getImg($apiData['slug']),
                'system' => [
                    'description' => $this->cleanDescription($apiData['description']),
                    'type'        => $this->getType($apiData['category']['name']),
                    'prix'        => $apiData['cost'],
                ],
            ];

            foreach ($apiData['attacks'] as $attack) {
                $subItemData = $itemData;

                if (1 < $nbAttacks) {
                    $subItemData['name'] .= ' - '.$attack['name'];
                }

                $subItemData['_id'] = $this->generateId($subItemData['name']);
                $subItemData['system'] = array_merge($itemData['system'], [
                    'portee'   => $this->getReach($attack['reach']),
                    'degats'   => [
                        'dice' => $attack['damage_dice'],
                        'fixe' => $attack['damage_bonus'],
                    ],
                    'violence' => [
                        'dice' => $attack['violence_dice'],
                        'fixe' => $attack['violence_bonus'],
                    ],
                ]);

                $items[] = $this->serializer->serialize(
                    $subItemData,
                    JsonEncoder::FORMAT,
                    [JsonEncode::OPTIONS => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]
                );
            }
        }

        try {
            $filesystem = new Filesystem();
            $filesystem->dumpFile('var/weapons.db', implode(PHP_EOL, $items));

            $io->success('Compendium généré.');
        } catch (\Throwable $e) {
            $io->error(sprintf('Erreur de génération du compendium : %s', $e->getMessage()));
        }

        return Command::SUCCESS;
    }

    private function getType(string $value): string
    {
        return match ($value) {
            'Arme à distance' => 'distance',
            'Arme de contact' => 'contact',
            default => throw new \InvalidArgumentException(sprintf('Type "%s" invalide', $value)),
        };
    }

    private function getImg(string $slug): string
    {
        static $existings = null;

        if (null === $existings) {
            $existings = [];
            $finder = new Finder();

            foreach ($finder->files()->in('var/files/weapons') as $file) {
                $existings[] = $file->getFilename();
            }
        }

        if (\in_array($slug.'.png', $existings, true)) {
            return 'systems/knight/assets/weapons/'.$slug.'.png';
        }

        return 'systems/knight/assets/icons/arme.svg';
    }
}
