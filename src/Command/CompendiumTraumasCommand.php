<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:compendium:traumas',
    description: 'Génération du compendium des traumas',
)]
class CompendiumTraumasCommand extends AbstractCompendiumCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $items = [];

        foreach ($this->getList() as $data) {
            $apiData = $this->getItem($data['id']);

            foreach ($apiData['traumas'] as $traumaData) {
                $itemData = $this->getBaseData();
                $itemData['_id'] = $this->generateId($traumaData['name']);
                $itemData['name'] = $traumaData['name'];
                $itemData['system']['description'] = $this->cleanDescription($traumaData['description']);
                $itemData['system']['gainEspoir']['value'] = $apiData['hop_recovered'];

                $items['base'][] = $this->serializeData($itemData);
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
        return 'trauma-category';
    }

    protected function getEmptyObjectPaths(): array
    {
        return [];
    }

    protected function getPluralizedType(): string
    {
        return 'traumas';
    }
}
