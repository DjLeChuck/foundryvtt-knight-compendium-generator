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
    private array $abilities;
    private array $specialties;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $items = [];

        $this->loadAbilities();
        $this->loadSpecialties();

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

            // Pas d'évolution pour la méta-armure Druid
            if ('druid' !== $apiData['slug']) {
                foreach ($apiData['abilities'] as $ability) {
                    $this->addAbility($ability, $itemData);
                }
            }

            foreach ($apiData['abilities'] as $special) {
                $this->addSpecial($special, $itemData);
            }

            $this->addEvolutions($apiData['evolutions'], $itemData);

            $items[$this->getArmourPack($apiData['slug'])][] = $this->serializeData($itemData);
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

    private function getArmourPack(string $value): string
    {
        return match ($value) {
            'warrior', 'barbarian', 'wizard', 'bard', 'ranger', 'rogue', 'warmaster', 'priest', 'paladin' => 'base',
            'psion', 'necromancer', 'sorcerer', 'monk' => '2038',
            'druid' => 'codex',
            'warlock', 'berserk', 'shaman' => 'atlas',
        };
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

    private function addEvolutions(array $evolutions, array &$itemData): void
    {
        $i = 0;

        foreach ($evolutions as $evolution) {
            $data = [
                'value'       => $evolution['unlock_at'],
                'description' => $this->cleanDescription($evolution['description']),
                'capacites'   => [],
                'special'     => [],
            ];

            foreach ($itemData['system']['capacites']['selected'] as $abilityName => $abilityData) {
                $data['capacites'][$abilityName] = $abilityData['evolutions'];
            }

            foreach ($itemData['system']['special']['selected'] as $abilityName => $abilityData) {
                $data['special'][$abilityName] = $abilityData['evolutions'];
            }

            $itemData['system']['evolutions']['liste'][(string) ++$i] = $data;
        }
    }

    private function loadAbilities(): void
    {
        $this->abilities = json_decode(
            file_get_contents('var/data/armour_abilities.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private function loadSpecialties(): void
    {
        $this->specialties = json_decode(
            file_get_contents('var/data/armour_specialties.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private function addAbility(array $ability, array &$itemData): void
    {
        $abilityName = $this->fixAbilityName($ability['name']);

        // Si le nom est null, on ignore
        if (null === $abilityName) {
            return;
        }

        foreach ($this->abilities as $abilityKey => $abilityData) {
            if ($abilityName === $abilityData['label']) {
                $itemData['system']['capacites']['selected'][$abilityKey] = $abilityData;

                return;
            }
        }

        throw new \InvalidArgumentException(sprintf('Capacité "%s" non traitable', $abilityName));
    }

    private function addSpecial(array $special, array &$itemData): void
    {
        $specialName = $this->fixSpecialName($special['name']);

        // Si le nom est null, on ignore
        if (null === $specialName) {
            return;
        }

        foreach ($this->specialties as $abilityKey => $abilityData) {
            if ($specialName === $abilityData['label']) {
                $itemData['system']['special']['selected'][$abilityKey] = $abilityData;

                return;
            }
        }

        throw new \InvalidArgumentException(sprintf('Spécialité "%s" non traitable', $specialName));
    }

    private function fixAbilityName(mixed $name): ?string
    {
        return match ($name) {
            'Fusil de précision polymorphe polycalibre Longbow' => 'Fusil de précision Longbow',
            'Mode Companion' => 'Mode Companions',
            'Armure sarcophage' => 'Armure Sarcophage',
            'Plus fort que la chair' => null, // Spécial
            'Il n\'y a plus d\'espoir' => null,
            'Contrecoups' => null, // Spécial
            'Imprégnation' => null, // Spécial
            default => $name,
        };
    }

    private function fixSpecialName(mixed $name): ?string
    {
        return match ($name) {
            'Contrecoups', 'Il n\'y a plus d\'espoir', 'Imprégnation', 'Lente et lourde' => $name,
            default => null,
        };
    }
}
