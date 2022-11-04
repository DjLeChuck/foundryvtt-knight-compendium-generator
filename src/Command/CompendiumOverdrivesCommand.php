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
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $items = [];

        foreach ($this->getList() as $data) {
            $apiData = $this->getItem($data['id']);
            $itemData = $this->getBaseData();
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

    protected function getEmptyObjectPaths(): array
    {
        return [];
    }
}
