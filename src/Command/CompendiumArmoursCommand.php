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
        $items = [];

        foreach ($this->getList() as $data) {
            $apiData = $this->getItem($data['id']);
            $itemData = $this->getBaseData();
            $itemData['_id'] = $this->generateId($apiData['name']);
            $itemData['name'] = $apiData['name'];
            $itemData['img'] = $this->getImg($apiData['slug']) ?? $itemData['img'];
            $itemData['system']['description'] = $this->getArmourDescription($apiData);
            $itemData['system']['generation'] = $apiData['generation'];
            $itemData['system']['armure']['value'] = $apiData['armour_points'];
            $itemData['system']['armure']['base'] = $apiData['armour_points'];
            $itemData['system']['champDeForce']['base'] = $apiData['force_field'];
            $itemData['system']['energie']['value'] = $apiData['energy_points'];
            $itemData['system']['energie']['base'] = $apiData['energy_points'];
            $itemData['system']['slots']['tete']['value'] = $apiData['slot_head'];
            $itemData['system']['slots']['brasGauche']['value'] = $apiData['slot_left_arm'];
            $itemData['system']['slots']['brasDroit']['value'] = $apiData['slot_right_arm'];
            $itemData['system']['slots']['torse']['value'] = $apiData['slot_torso'];
            $itemData['system']['slots']['jambeGauche']['value'] = $apiData['slot_left_leg'];
            $itemData['system']['slots']['jambeDroite']['value'] = $apiData['slot_right_leg'];
            $itemData['system']['evolutions']['paliers'] = \count($apiData['evolutions']);

            foreach ($apiData['overdrives'] as $overdrive) {
                $this->addOverdrive($overdrive['characteristic'], $itemData);
            }

            foreach ($apiData['evolutions'] as $evolution) {
                $this->addEvolution($evolution, $itemData);
            }

            $items[] = $this->serializeData($itemData);
        }

        try {
            $this->dumpCompendium($items);

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

    private function addOverdrive(array $overdrive, array &$itemData): void
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

        ++$itemData['system']['overdrives'][$aspect]['liste'][$name]['value'];
    }

    private function addEvolution(array $evolution, array &$itemData): void
    {
        $itemData['system']['evolutions']['liste'][] = [
            'value'       => $evolution['unlock_at'],
            'description' => $this->cleanDescription($evolution['description']),
            'capacites'   => [],
            'special'     => [],
        ];
    }
}
