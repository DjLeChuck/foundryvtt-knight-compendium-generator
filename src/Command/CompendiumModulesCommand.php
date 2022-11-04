<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:compendium:modules',
    description: 'Génération du compendium des modules',
)]
class CompendiumModulesCommand extends AbstractCompendiumCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $items = [];

        foreach ($this->getList() as $data) {
            $apiData = $this->getItem($data['id']);
            $slotData = current($apiData['slots']);
            $nbLevels = \count($apiData['levels']);
            $itemData = $this->getBaseData();
            $itemData['name'] = $apiData['name'];
            $itemData['img'] = $this->getImg($apiData['slug']) ?? $itemData['img'];
            $itemData['system']['categorie'] = $this->getCategory($apiData['category']['name']);
            $itemData['system']['slots']['tete'] = false !== $slotData ? $slotData['head'] : 0;
            $itemData['system']['slots']['brasGauche'] = false !== $slotData ? $slotData['left_arm'] : 0;
            $itemData['system']['slots']['brasDroit'] = false !== $slotData ? $slotData['right_arm'] : 0;
            $itemData['system']['slots']['torse'] = false !== $slotData ? $slotData['torso'] : 0;
            $itemData['system']['slots']['jambeGauche'] = false !== $slotData ? $slotData['left_leg'] : 0;
            $itemData['system']['slots']['jambeDroite'] = false !== $slotData ? $slotData['right_leg'] : 0;

            foreach ($apiData['levels'] as $level) {
                $levelData = $itemData;

                if (1 < $nbLevels) {
                    $levelData['name'] .= ' niv. '.$level['level'];
                }

                $levelData['_id'] = $this->generateId($levelData['name']);
                $levelData['system']['description'] = $this->cleanDescription($level['description']);
                $levelData['system']['prix'] = $level['cost'];
                $levelData['system']['activation'] = $this->getActivation($level['activation']);
                $levelData['system']['rarete'] = $this->getRarity($level['rarity']);
                $levelData['system']['portee'] = $this->getReach($level['reach']);
                $levelData['system']['energie']['tour'] = ['value' => 0, 'label' => 'Tour'];
                $levelData['system']['energie']['minute'] = ['value' => 0, 'label' => 'Minute'];
                $levelData['system']['energie']['supplementaire'] = 0;

                $this->setEnergy($level, $levelData);
                $this->setWeaponData($level, $levelData);
                $this->setEffects($level, $levelData);

                $items[$levelData['system']['rarete']][] = $this->serializeData($levelData);
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
        return 'module';
    }

    protected function getEmptyObjectPaths(): array
    {
        return [
            'system.listes', 'system.labels',
            'system.aspects.chair.liste.deplacement',
            'system.aspects.chair.liste.force',
            'system.aspects.chair.liste.endurance',
            'system.aspects.bete.liste.deplacement',
            'system.aspects.bete.liste.force',
            'system.aspects.bete.liste.endurance',
            'system.aspects.machine.liste.deplacement',
            'system.aspects.machine.liste.force',
            'system.aspects.machine.liste.endurance',
            'system.aspects.dame.liste.deplacement',
            'system.aspects.dame.liste.force',
            'system.aspects.dame.liste.endurance',
            'system.aspects.masque.liste.deplacement',
            'system.aspects.masque.liste.force',
            'system.aspects.masque.liste.endurance',
            'system.pnj.modele.jetSpecial.liste',
            'system.pnj.liste',
        ];
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

    private function setEnergy(array $data, array &$itemData): void
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
                $itemData['system']['energie'][$rows[$index]] = $data['energy'];
            } else {
                $itemData['system']['energie'][$rows[$index]] = [
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

    private function setWeaponData(array $data, array &$itemData): void
    {
        if (null === $data['reach']) {
            return;
        }

        $itemData['system']['arme']['has'] = true;
        $itemData['system']['arme']['portee'] = $this->getReach($data['reach']);
        $itemData['system']['arme']['type'] = $this->getWeaponTypeFromReach($itemData['system']['arme']['portee']);
        $itemData['system']['arme']['degats']['dice'] = $data['damage_dice'];
        $itemData['system']['arme']['degats']['fixe'] = $data['damage_bonus'];
        $itemData['system']['arme']['violence']['dice'] = $data['violence_dice'];
        $itemData['system']['arme']['violence']['fixe'] = $data['violence_bonus'];
    }

    private function setEffects(array $data, array &$itemData): void
    {
        foreach ($data['effects'] as $effect) {
            // On ajoute les effets que s'il n'y a pas de choix à faire
            if (0 !== $effect['choice_number']) {
                continue;
            }

            if (null !== $effect['effect']) {
                // Zakarik : "Le Ignore couvert, ce n'est pas un effet des livres, c'est un effet propre à Knight JDR système, c'est pour ça qu'il n'est pas dans la liste"
                if ($this->ignoreEffect($effect['effect']['name'])) {
                    continue;
                }

                $value = $this->getEffect($effect['effect']['slug']);

                if (0 < $effect['effect_level']) {
                    $value .= ' '.$effect['effect_level'];
                }

                $itemData['system']['arme']['effets']['raw'][] = $value;
            }

            if (0 < $effect['damage']) {
                $itemData['system']['arme']['effets']['custom'][] = $this->buildCustomEffect(
                    $effect,
                    'degats',
                    $effect['damage']
                );
            }

            if (0 < $effect['violence']) {
                $itemData['system']['arme']['effets']['custom'][] = $this->buildCustomEffect(
                    $effect,
                    'violence',
                    $effect['violence']
                );
            }
        }
    }

    private function buildCustomEffect(array $effect, string $type, int $throwValue): array
    {
        $baseEffect = $this->getCustomEffectTemplate();
        $baseEffect[$type]['jet'] = $throwValue;

        if (!empty($effect['effect_condition'])) {
            $baseEffect[$type]['conditionnel']['has'] = true;
            $baseEffect[$type]['conditionnel']['condition'] = $effect['effect_condition'];
        }

        return $baseEffect;
    }
}
