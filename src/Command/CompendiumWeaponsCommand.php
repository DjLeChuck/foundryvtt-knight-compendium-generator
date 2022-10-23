<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
            $itemData = $this->getBaseData();
            $itemData['name'] = $apiData['name'];
            $itemData['img'] = $this->getImg($apiData['slug']) ?? $itemData['img'];
            $itemData['system']['description'] = $this->cleanDescription($apiData['description']);
            $itemData['system']['type'] = $this->getWeaponType($apiData['category']['name']);
            $itemData['system']['prix'] = $apiData['cost'];

            foreach ($apiData['attacks'] as $attack) {
                $subItemData = $itemData;

                if (1 < $nbAttacks) {
                    $subItemData['name'] .= ' - '.$attack['name'];
                }

                $subItemData['_id'] = $this->generateId($subItemData['name']);
                $subItemData['system']['portee'] = $this->getReach($attack['reach']);
                $subItemData['system']['degats']['dice'] = $attack['damage_dice'];
                $subItemData['system']['degats']['fixe'] = $attack['damage_bonus'];
                $subItemData['system']['violence']['dice'] = $attack['violence_dice'];
                $subItemData['system']['violence']['fixe'] = $attack['violence_bonus'];

                $this->addEffects($attack['effects'], $subItemData);

                $items[] = $this->serializeData($subItemData);
            }
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
        return 'weapon';
    }

    private function getWeaponType(string $value): string
    {
        return match ($value) {
            'Arme à distance' => 'distance',
            'Arme de contact' => 'contact',
            default => throw new \InvalidArgumentException(sprintf('Type "%s" invalide', $value)),
        };
    }

    private function addEffects(array $effects, array &$itemData): void
    {
        foreach ($effects as $effect) {
            // Zakarik : "Le Ignore couvert, c'est pas un effet des livres, c'est un effet propre a Knight JDR système, c'est pour ça qu'il n'est pas dans la liste"
            if ($this->ignoreEffect($effect['effect']['name'])) {
                continue;
            }

            $value = $this->getEffect($effect['effect']['slug']);

            if (0 < $effect['effect_level']) {
                $value .= ' '.$effect['effect_level'];
            }

            $itemData['system']['effets']['raw'][] = $value;
        }
    }

    private function ignoreEffect(string $value): bool
    {
        $value = str_replace(' X', '', $value);

        return str_starts_with($value, '[') && str_ends_with($value, ']');
    }

    private function getEffect(string $value): string
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
