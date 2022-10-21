<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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

        foreach ($this->getList() as $data) {
            $apiData = $this->getItem($data['id']);
            $armourData = $this->getBaseData();
            $armourData['_id'] = $this->generateId($apiData['name']);
            $armourData['name'] = $apiData['name'];
            $armourData['img'] = $this->getImg($apiData['slug']) ?? $armourData['img'];
            $armourData['system']['description'] = $this->getArmourDescription($apiData);
            $armourData['system']['generation'] = $apiData['generation'];
            $armourData['system']['armure']['value'] = $apiData['armour_points'];
            $armourData['system']['armure']['base'] = $apiData['armour_points'];
            $armourData['system']['champDeForce']['base'] = $apiData['force_field'];
            $armourData['system']['energie']['value'] = $apiData['energy_points'];
            $armourData['system']['energie']['base'] = $apiData['energy_points'];
            $armourData['system']['slots']['tete']['value'] = $apiData['slot_head'];
            $armourData['system']['slots']['brasGauche']['value'] = $apiData['slot_left_arm'];
            $armourData['system']['slots']['brasDroit']['value'] = $apiData['slot_right_arm'];
            $armourData['system']['slots']['torse']['value'] = $apiData['slot_torso'];
            $armourData['system']['slots']['jambeGauche']['value'] = $apiData['slot_left_leg'];
            $armourData['system']['slots']['jambeDroite']['value'] = $apiData['slot_right_leg'];
            $armourData['system']['evolutions']['paliers'] = \count($apiData['evolutions']);

            foreach ($apiData['overdrives'] as $overdrive) {
                $this->addOverdrive($overdrive['characteristic'], $armourData);
            }

            foreach ($apiData['evolutions'] as $evolution) {
                $this->addEvolution($evolution, $armourData);
            }

            $armours[] = $this->serializeData($armourData);
        }

        try {
            $this->dumpCompendium($armours);

            $io->success('Compendium généré.');
        } catch (\Throwable $e) {
            $io->error(sprintf('Erreur de génération du compendium : %s', $e->getMessage()));
        }

        return Command::SUCCESS;
    }

    protected function getType(): string
    {
        return 'armour';
    }

    private function getArmourDescription(array $data): string
    {
        $parts = [
            $data['background_description'],
            $data['technical_description'],
            $data['additional_notes'],
        ];

        return implode(
            '<br /><br />',
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
            'description' => $this->cleanDescription($evolution['description']),
            'capacites'   => [],
            'special'     => [],
        ];
    }
}
