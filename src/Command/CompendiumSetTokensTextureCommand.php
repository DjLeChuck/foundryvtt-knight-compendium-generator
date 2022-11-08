<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'app:compendium:set-tokens-texture',
    description: 'Modifie la texture des tokens pour prendre la valeur de l\'image de l\'acteur.',
)]
class CompendiumSetTokensTextureCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('module', InputArgument::REQUIRED, 'Répertoire du module à traiter');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $modulePath = $input->getArgument('module');
        $filesystem = new Filesystem();

        if (!$filesystem->exists($modulePath)) {
            $io->error(sprintf('Le répertoire "%s" n\'existe pas.', $modulePath));

            return self::FAILURE;
        }

        try {
            $moduleData = json_decode(
                file_get_contents($modulePath.'/module.json'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\Throwable) {
            $io->error('Impossible de lire le fichier "module.json" du module.');

            return self::FAILURE;
        }

        $actorPacks = array_filter($moduleData['packs'], static function ($packData) {
            return 'Actor' === $packData['type'];
        });

        if (empty($actorPacks)) {
            $io->info('Aucun pack d\'Actor à traiter.');

            return self::SUCCESS;
        }

        foreach ($actorPacks as $packData) {
            $packPath = $modulePath.'/'.$packData['path'];
            if (!$filesystem->exists($packPath)) {
                $io->warning(sprintf('Chemin du pack "%s" invalide.', $packData['label']));

                continue;
            }

            $this->processPack($packPath, $io);
        }

        $io->success('Traitement terminé.');

        return Command::SUCCESS;
    }

    private function processPack(string $path, SymfonyStyle $io): void
    {
        $handle = fopen($path, 'rb');
        if ($handle) {
            $io->info(sprintf('Traitement du pack "%s"', $path));

            $newContent = '';

            while (($line = fgets($handle)) !== false) {
                $imgMatch = [];
                preg_match('`"img":"(.+?)"`', $line, $imgMatch);

                if (!isset($imgMatch[1])) {
                    $io->error(sprintf(
                        'Impossible de détecter l\'image sur la ligne commençant par "%s"',
                        mb_substr($line, 0, 100)
                    ));

                    continue;
                }

                $newContent .= $this->setTokenTexture($imgMatch[1], $line);
            }

            fclose($handle);

            $filesystem = new Filesystem();
            $filesystem->dumpFile($path, $newContent);
        }
    }

    private function setTokenTexture(string $img, string $line): string
    {
        $replacement = sprintf('"texture":{"src":"%s"', $img);

        return preg_replace('`"texture":\{"src":".+?"`', $replacement, $line);
    }
}
