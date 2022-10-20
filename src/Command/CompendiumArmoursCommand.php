<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

#[AsCommand(
    name: 'app:compendium:armours',
    description: 'Génération du compendium des méta-armures',
)]
class CompendiumArmoursCommand extends AbstractCompendiumCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $armours = [];

        foreach ($this->api->get('armour') as $data) {
            $apiData = $this->api->get('armour/'.$data['id']);

            $armourData = [
                '_id'    => $this->generateId($apiData['name']),
                'name'   => $apiData['name'],
                'type'   => 'armure',
                'img'    => 'systems/knight/assets/armours/'.$apiData['slug'].'.png',
                'system' => [
                    'description'  => $this->getArmourDescription($apiData),
                    'generation'   => $apiData['generation'],
                    'armure'       => [
                        'value' => $apiData['armour_points'],
                        'base'  => $apiData['armour_points'],
                    ],
                    'champDeForce' => [
                        'value' => $apiData['force_field'],
                        'base'  => $apiData['force_field'],
                    ],
                    'energie'      => [
                        'value' => $apiData['energy_points'],
                        'base'  => $apiData['energy_points'],
                    ],
                    'slots'        => [
                        'tete'        => [
                            'value' => $apiData['slot_head'],
                        ],
                        'brasGauche'  => [
                            'value' => $apiData['slot_left_arm'],
                        ],
                        'brasDroit'   => [
                            'value' => $apiData['slot_right_arm'],
                        ],
                        'torse'       => [
                            'value' => $apiData['slot_torso'],
                        ],
                        'jambeGauche' => [
                            'value' => $apiData['slot_left_leg'],
                        ],
                        'jambeDroite' => [
                            'value' => $apiData['slot_right_leg'],
                        ],
                    ],
                    'overdrives'   => [
                        'chair'   => [
                            'liste' => [
                                'deplacement' => [
                                    'value' => 0,
                                ],
                                'force'       => [
                                    'value' => 0,
                                ],
                                'endurance'   => [
                                    'value' => 0,
                                ],
                            ],
                        ],
                        'bete'    => [
                            'liste' => [
                                'hargne'   => [
                                    'value' => 0,
                                ],
                                'combat'   => [
                                    'value' => 0,
                                ],
                                'instinct' => [
                                    'value' => 0,
                                ],
                            ],
                        ],
                        'machine' => [
                            'liste' => [
                                'tir'       => [
                                    'value' => 0,
                                ],
                                'savoir'    => [
                                    'value' => 0,
                                ],
                                'technique' => [
                                    'value' => 0,
                                ],
                            ],
                        ],
                        'dame'    => [
                            'liste' => [
                                'aura'      => [
                                    'value' => 0,
                                ],
                                'parole'    => [
                                    'value' => 0,
                                ],
                                'sangFroid' => [
                                    'value' => 0,
                                ],
                            ],
                        ],
                        'masque'  => [
                            'liste' => [
                                'discretion' => [
                                    'value' => 0,
                                ],
                                'dexterite'  => [
                                    'value' => 0,
                                ],
                                'perception' => [
                                    'value' => 0,
                                ],
                            ],
                        ],
                    ],
                    'evolutions'   => [
                        'paliers'  => \count($apiData['evolutions']),
                        'aAcheter' => ['value' => true],
                        'liste'    => [],
                    ],
                ],
            ];

            foreach ($apiData['overdrives'] as $overdrive) {
                $this->addOverdrive($overdrive['characteristic'], $armourData);
            }

            foreach ($apiData['evolutions'] as $evolution) {
                $this->addEvolution($evolution, $armourData);
            }

            $armours[] = $this->serializer->serialize(
                $armourData,
                JsonEncoder::FORMAT,
                [JsonEncode::OPTIONS => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]
            );
        }

        try {
            $filesystem = new Filesystem();
            $filesystem->dumpFile('var/armours.db', implode(PHP_EOL, $armours));

            $io->success('Compendium généré.');
        } catch (\Throwable $e) {
            $io->error(sprintf('Erreur de génération du compendium : %s', $e->getMessage()));
        }

        return Command::SUCCESS;
    }

    private function getArmourDescription(array $data): string
    {
        $parts = [
            $data['background_description'],
            $data['technical_description'],
            $data['additional_notes'],
        ];

        return implode(
            PHP_EOL,
            array_map(fn($value) => $this->cleanDescription($value), $parts)
        );
    }

    private function addOverdrive(array $overdrive, array &$armourData): void
    {
        switch ($overdrive['name']) {
            case 'Déplacement':
                $aspect = 'chair';
                $name = 'deplacement';
                break;
            case 'Force':
                $aspect = 'chair';
                $name = 'force';
                break;
            case 'Endurance':
                $aspect = 'chair';
                $name = 'endurance';
                break;
            case 'Hargne':
                $aspect = 'bete';
                $name = 'hargne';
                break;
            case 'Combat':
                $aspect = 'bete';
                $name = 'combat';
                break;
            case 'Instinct':
                $aspect = 'bete';
                $name = 'instinct';
                break;
            case 'Tir':
                $aspect = 'machine';
                $name = 'tir';
                break;
            case 'Savoir':
                $aspect = 'machine';
                $name = 'savoir';
                break;
            case 'Technique':
                $aspect = 'machine';
                $name = 'technique';
                break;
            case 'Aura':
                $aspect = 'dame';
                $name = 'aura';
                break;
            case 'Parole':
                $aspect = 'dame';
                $name = 'parole';
                break;
            case 'Sang-froid':
                $aspect = 'dame';
                $name = 'sangFroid';
                break;
            case 'Discrétion':
                $aspect = 'masque';
                $name = 'discretion';
                break;
            case 'Dextérité':
                $aspect = 'masque';
                $name = 'dexterite';
                break;
            case 'Perception':
                $aspect = 'masque';
                $name = 'perception';
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Overdrive "%s" non traitable', $overdrive['name']));
        }

        ++$armourData['system']['overdrives'][$aspect]['liste'][$name]['value'];
    }

    private function addEvolution(array $evolution, array &$armourData): void
    {
        $armourData['system']['evolutions']['liste'][] = [
            'value'       => $evolution['unlock_at'],
            'description' => $evolution['description'],
        ];
    }
}
