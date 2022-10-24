<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:compendium:npc-capacities',
    description: 'Génération du compendium des capacités des PNJs',
)]
class CompendiumNpcCapacitiesCommand extends AbstractCompendiumCommand
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
            $itemData['system']['description'] = $this->cleanDescription($apiData['description']);

            $items['base'][] = $this->serializeData($itemData);
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
        return 'npc-capacity';
    }

    protected function getEmptyObjectPaths(): array
    {
        return [
            'system.aspects.chair', 'system.aspects.bete', 'system.aspects.machine',
            'system.aspects.dame', 'system.aspects.masque',
        ];
    }
}
