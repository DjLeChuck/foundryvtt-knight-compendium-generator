<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:compendium:ai-disadvantages',
    description: 'Génération du compendium des inconvénients des IA',
)]
class CompendiumAIDisadvantagesCommand extends AbstractCompendiumCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $items = [];

        foreach ($this->getList() as $data) {
            $apiData = $this->getItem($data['id']);
            $itemData = $this->getBaseData();
            $itemData['_id'] = $this->generateId($apiData['ai_disadvantage_name']);
            $itemData['name'] = $apiData['ai_disadvantage_name'];
            $itemData['system']['type'] = 'ia';
            $itemData['system']['description'] = $this->cleanDescription($apiData['ai_disadvantage']);

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
        return 'ai-disadvantage';
    }

    protected function getEmptyObjectPaths(): array
    {
        return [];
    }

    protected function getList(): array
    {
        return $this->api->get('arcana');
    }

    protected function getItem(int $id): array
    {
        return $this->api->get(sprintf('arcana/%u', $id));
    }

    protected function getTplName(): string
    {
        return 'disadvantage_tpl.json';
    }
}
