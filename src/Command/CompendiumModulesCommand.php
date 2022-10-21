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
    name: 'app:compendium:modules',
    description: 'Génération du compendium des modules',
)]
class CompendiumModulesCommand extends AbstractCompendiumCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $modules = [];

        foreach ($this->api->get('module') as $data) {
            $apiData = $this->api->get('module/'.$data['id']);
            $slotData = current($apiData['slots']);
            $nbLevels = \count($apiData['levels']);
            $moduleData = [
                'name'   => $apiData['name'],
                'type'   => 'module',
                'img'    => $this->getImg($apiData['slug']),
                'system' => [
                    'categorie' => $this->getCategory($apiData['category']['name']),
                    'slots'     => [
                        'tete'        => false !== $slotData ? $slotData['head'] : 0,
                        'brasGauche'  => false !== $slotData ? $slotData['left_arm'] : 0,
                        'brasDroit'   => false !== $slotData ? $slotData['right_arm'] : 0,
                        'torse'       => false !== $slotData ? $slotData['torso'] : 0,
                        'jambeGauche' => false !== $slotData ? $slotData['left_leg'] : 0,
                        'jambeDroite' => false !== $slotData ? $slotData['right_leg'] : 0,
                    ],
                ],
            ];

            foreach ($apiData['levels'] as $level) {
                $levelData = $moduleData;

                if (1 < $nbLevels) {
                    $levelData['name'] .= ' niv. '.$level['level'];
                }

                $levelData['_id'] = $this->generateId($levelData['name']);
                $levelData['system'] = array_merge($levelData['system'], [
                    'description' => $this->cleanDescription($level['description']),
                    'prix'        => $level['cost'],
                    'activation'  => $this->getActivation($level['activation']),
                    'rarete'      => $this->getRarity($level['rarity']),
                    'portee'      => $this->getReach($level['reach']),
                    'energie'     => [
                        'tour'           => ['value' => 0, 'label' => 'Tour'],
                        'minute'         => ['value' => 0, 'label' => 'Minute'],
                        'supplementaire' => 0,
                    ],
                ]);

                $this->setEnergy($level, $levelData);

                $modules[] = $this->serializer->serialize(
                    $levelData,
                    JsonEncoder::FORMAT,
                    [JsonEncode::OPTIONS => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]
                );
            }
        }

        try {
            $filesystem = new Filesystem();
            $filesystem->dumpFile('var/modules.db', implode(PHP_EOL, $modules));

            $io->success('Compendium généré.');
        } catch (\Throwable $e) {
            $io->error(sprintf('Erreur de génération du compendium : %s', $e->getMessage()));
        }

        return Command::SUCCESS;
    }

    private function getCategory(string $value): string
    {
        return match ($value) {
            'Amélioration' => 'amelioration',
            'Automatisé' => 'automatise',
            'Contact' => 'contact',
            'Distance' => 'distance',
            'Défense' => 'defense',
            'Déplacement' => 'deplacement',
            'Prestige Aigle' => 'aigle',
            'Prestige Cerf' => 'cerf',
            'Prestige Cheval' => 'cheval',
            'Prestige Corbeau' => 'corbeau',
            'Prestige Dragon' => 'dragon',
            'Prestige Faucon' => 'faucon',
            'Prestige Lion' => 'lion',
            'Prestige Loup' => 'loup',
            'Prestige Ours' => 'ours',
            'Prestige Sanglier' => 'sanglier',
            'Prestige Serpent' => 'serpent',
            'Prestige Taureau' => 'taureau',
            'Tactique' => 'tactique',
            'Utilitaire' => 'utilitaire',
            'Visée' => 'visée',
            default => throw new \InvalidArgumentException(sprintf('Catégorie "%s" invalide', $value)),
        };
    }

    private function getImg(string $slug): string
    {
        static $existings = null;

        if (null === $existings) {
            $existings = [];
            $finder = new Finder();

            foreach ($finder->files()->in('var/files/modules') as $file) {
                $existings[] = $file->getFilename();
            }
        }

        if (\in_array($slug.'.png', $existings, true)) {
            return 'systems/knight/assets/modules/'.$slug.'.png';
        }

        return 'systems/knight/assets/icons/module.svg';
    }

    private function getActivation(?string $value): ?string
    {
        return match ($value) {
            null, 'Aucune' => 'aucune',
            'Déplacement' => 'deplacement',
            'Combat' => 'combat',
            'Tour complet' => 'tourComplet',
            default => throw new \InvalidArgumentException(sprintf('Activation "%s" invalide', $value)),
        };
    }

    private function getRarity(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        return [
                   'Standard' => 'standard',
                   'Avancé'   => 'avance',
                   'Rare'     => 'rare',
                   'Prestige' => 'prestige',
               ][$value];
    }

    private function setEnergy(array $data, array &$moduleData): void
    {
        static $rows = [
            0 => 'tour',
            1 => 'minute',
            2 => 'supplementaire',
        ];
        $parts = explode(' / ', $data['duration']);

        foreach ($parts as $index => $label) {
            $label = $this->fixEnergyLabel($label);

            if (2 === $index) {
                $moduleData['system']['energie'][$rows[$index]] = $data['energy'];
            } else {
                $moduleData['system']['energie'][$rows[$index]] = [
                    'value' => $data['energy'],
                    'label' => $label,
                ];
            }
        }
    }

    private function fixEnergyLabel(string $label): string
    {
        if (!str_starts_with($label, '1 ') && str_ends_with($label, ' tour')) {
            $label .= 's';
        }

        return $label;
    }
}
