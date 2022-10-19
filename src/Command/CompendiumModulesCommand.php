<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:compendium:modules',
    description: 'Génération du compendium des modules',
)]
class CompendiumModulesCommand extends Command
{
    private HttpClientInterface $client;
    private SerializerInterface $serializer;

    public function __construct(HttpClientInterface $knightClient, SerializerInterface $serializer)
    {
        $this->client = $knightClient;
        $this->serializer = $serializer;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $response = $this->client->request('GET', 'module');
        $modules = [];

        foreach ($response->toArray() as $data) {
            $itemResponse = $this->client->request('GET', 'module/'.$data['id']);
            $apiData = $itemResponse->toArray();
            $slotData = current($apiData['slots']);
            $nbLevels = \count($apiData['levels']);
            $moduleData = [
                'name'   => $apiData['name'],
                'type'   => 'module',
                'img'    => 'systems/knight/assets/icons/module.svg',
                'system' => [
                    'slots' => [
                        'tete'        => false !== $slotData ? $slotData['head'] : 0,
                        'brasGauche'  => false !== $slotData ? $slotData['left_arm'] : 0,
                        'brasDroit'   => false !== $slotData ? $slotData['right_arm'] : 0,
                        'torse'       => false !== $slotData ? $slotData['torso'] : 0,
                        'jambeGauche' => false !== $slotData ? $slotData['left_leg'] : 0,
                        'jambeDroite' => false !== $slotData ? $slotData['right_leg'] : 0,
                    ],
                ],
            ];

            foreach ($apiData['levels'] as $level) {
                $levelData = $moduleData;

                if (1 < $nbLevels) {
                    $levelData['name'] .= ' niv. '.$level['level'];
                }

                $levelData['_id'] = $this->generateId($levelData['name']);
                $levelData['system'] = array_merge($levelData['system'], [
                    'description' => $level['description'],
                    'prix'        => $level['cost'],
                    'activation'  => $this->getActivation($level['activation']),
                    'rarete'      => $this->getRarity($level['rarity']),
                ]);

                $modules[] = $this->serializer->serialize(
                    $levelData,
                    JsonEncoder::FORMAT,
                    [JsonEncode::OPTIONS => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]
                );
            }
        }

        try {
            $filesystem = new Filesystem();
            $filesystem->dumpFile('var/modules.db', implode(PHP_EOL, $modules));

            $io->success('Compendium généré.');
        } catch (\Throwable $e) {
            $io->error(sprintf('Erreur de génération du compendium : %s', $e->getMessage()));
        }

        return Command::SUCCESS;
    }

    private function getActivation(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        return [
                   'Aucune'       => 'aucune',
                   'Déplacement'  => 'deplacement',
                   'Combat'       => 'combat',
                   'Tour complet' => 'combat', // ?
               ][$value];
    }

    private function getRarity(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        return [
                   'Standard' => 'standard',
                   'Avancé'   => 'avance',
                   'Rare'     => 'rare',
                   'Prestige' => 'prestige',
               ][$value];
    }

    function generateId($input, $length = 16)
    {
        // Create a raw binary sha256 hash and base64 encode it.
        $hash_base64 = base64_encode(hash('sha256', $input, true));
        // Replace non-urlsafe chars to make the string urlsafe.
        $hash_urlsafe = strtr($hash_base64, '+/', '-_');
        // Trim base64 padding characters from the end.
        $hash_urlsafe = rtrim($hash_urlsafe, '=');

        // Shorten the string before returning.
        return substr($hash_urlsafe, 0, $length);
    }
}
