<?php

namespace App\Command;

use App\Api;
use App\MediaGrabber;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:media:grab',
    description: 'Télécharge tous les assets possibles pour les compendium',
)]
class MediaGrabCommand extends Command
{
    private MediaGrabber $grabber;
    private Api $api;

    public function __construct(MediaGrabber $grabber, Api $api)
    {
        $this->grabber = $grabber;
        $this->api = $api;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Récupération des assets des méta-armures...');
        foreach ($this->api->get('armour') as $item) {
            $this->grabber->grab(MediaGrabber::TYPE_ARMOUR, $item['slug']);
        }

        $io->info('Récupération des assets des modules...');
        foreach ($this->api->get('module') as $item) {
            $this->grabber->grab(MediaGrabber::TYPE_MODULE, $item['slug']);
        }

        $io->info('Récupération des assets des armes...');
        foreach ($this->api->get('weapon') as $item) {
            $this->grabber->grab(MediaGrabber::TYPE_WEAPON, $item['slug']);
        }

        $io->success('Assets récupérés !');

        return Command::SUCCESS;
    }
}
