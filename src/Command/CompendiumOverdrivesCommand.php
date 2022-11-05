<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:compendium:overdrives',
    description: 'Génération du compendium des overdrives',
)]
class CompendiumOverdrivesCommand extends AbstractCompendiumCommand
{
    private \Transliterator $transliterator;

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        if (null === $transliterator) {
            throw new \RuntimeException('Impossible d\'instancier le translitérateur.');
        }

        $this->transliterator = $transliterator;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $items = [];

        foreach ($this->getList() as $data) {
            $apiData = $this->getItem($data['id']);
            $itemData = $this->getBaseData();

            $name = sprintf('%s - Niv. %u', $apiData['characteristic']['name'], $apiData['level']);
            $itemData['_id'] = $this->generateId($name);
            $itemData['name'] = $name;
            $itemData['system']['description'] = $this->cleanDescription($apiData['description']);
            $itemData['system']['rarete'] = $this->getRarity($apiData['rarity']);
            $itemData['system']['prix'] = $apiData['cost'];
            $itemData['system']['permanent'] = true;
            $itemData['system']['overdrives']['has'] = true;
            $aspectName = $this->getSlugifiedName($apiData['characteristic']['aspect']);
            $caracName = $this->getSlugifiedName($apiData['characteristic']['name']);
            $itemData['system']['overdrives']['aspects'][$aspectName][$caracName] = $apiData['level'];

            $items[$itemData['system']['rarete']][] = $this->serializeData($itemData);
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
        return 'overdrive';
    }

    protected function getTplName(): string
    {
        return 'module_tpl.json';
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

    private function getSlugifiedName(string $value): string
    {
        $value = trim(
            preg_replace('/[^a-z0-9]+/', '', $this->transliterator->transliterate($value)),
            ''
        );

        return 'sangfroid' === $value ? 'sangFroid' : $value;
    }
}
