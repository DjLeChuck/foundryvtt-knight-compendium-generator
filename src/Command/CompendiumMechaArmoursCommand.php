<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:compendium:mecha-armours',
    description: 'Génération du compendium des mécha-armures',
)]
class CompendiumMechaArmoursCommand extends AbstractCompendiumCommand
{
    private array $modules;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $items = [];

        $this->loadModules();

        foreach ($this->getList() as $data) {
            $apiData = $this->getItem($data['id']);
            $itemData = $this->getBaseData();
            $itemData['_id'] = $this->generateId($apiData['name']);
            $itemData['name'] = $apiData['name'];
            $itemData['prototypeToken']['name'] = $itemData['name'];
            $itemData['system']['description'] = $this->cleanDescription($apiData['description']);
            $itemData['system']['vitesse']['base'] = $apiData['speed'];
            $itemData['system']['manoeuvrabilite']['base'] = $apiData['maneuverability'];
            $itemData['system']['puissance']['base'] = $apiData['power'];
            $itemData['system']['senseurs']['base'] = $apiData['sensors'];
            $itemData['system']['systemes']['base'] = $apiData['systems'];
            $itemData['system']['resilience']['base'] = $apiData['resilience'];
            $itemData['system']['blindage']['value'] = $apiData['armour_plating'];
            $itemData['system']['blindage']['max'] = $apiData['armour_plating'];
            $itemData['system']['champDeForce']['base'] = $apiData['force_field'];

            $this->addConfigurations($apiData, $itemData);
            $this->addModules(
                'base',
                $apiData['actions'],
                $itemData['system']['configurations']['liste']['base']['modules']
            );

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
        return 'mecha-armour';
    }

    protected function getEmptyObjectPaths(): array
    {
        return [
            'system.configurations.liste.base.modules',
            'system.configurations.liste.c1.modules',
            'system.configurations.liste.c2.modules',
            'prototypeToken.flags',
        ];
    }

    private function loadModules(): void
    {
        $this->modules = json_decode(
            file_get_contents('var/data/mecha-armours_modules.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private function addConfigurations(array $apiData, array &$itemData): void
    {
        $i = 0;

        foreach ($apiData['configurations'] as $configurationData) {
            $itemData['system']['configurations']['liste']['c'.(++$i)]['name'] = $configurationData['name'];

            $this->addModules(
                'c'.$i,
                $configurationData['actions'],
                $itemData['system']['configurations']['liste']['c'.$i]['modules']
            );
        }
    }

    protected function getModule(string $value): string
    {
        return match ($value) {
            'Boucliers Amrita' => 'bouclierAmrita',
            'Canons Magma' => 'canonMagma',
            'Canon Métatron' => 'canonMetatron',
            'Canon Noé' => 'canonNoe',
            'Choc sonique' => 'chocSonique',
            'Curse' => 'curse',
            'Drones d\'airain' => 'dronesAirain',
            'Drones d\'évacuation' => 'dronesEvacuation',
            'Lames cinétiques géantes' => 'lamesCinetiquesGeantes',
            'Missiles Jericho' => 'missilesJericho',
            'Mitrailleuses Surtur' => 'mitrailleusesSurtur',
            'Mode Siege Tower' => 'modeSiegeTower',
            'Module Emblem' => 'moduleEmblem',
            'Module Inferno' => 'moduleInferno',
            'Module Wraith' => 'moduleWraith',
            'Nanobrume' => 'nanoBrume',
            'Offering' => 'offering',
            'Pod d\'invulnérabilité' => 'podInvulnerabilite',
            'Pod Miracle' => 'podMiracle',
            'Poings soniques' => 'poingsSoniques',
            'Saut Mark IV' => 'sautMarkIV',
            'Souffle démoniaque' => 'souffleDemoniaque',
            'Station de défense automatisée' => 'stationDefenseAutomatise',
            'Tourelles lasers automatisées' => 'tourellesLasersAutomatisees',
            'Vague de soin' => 'vagueSoin',
            'Vol Mark IV' => 'volMarkIV',
            default => throw new \InvalidArgumentException(sprintf('Module "%s" invalide', $value)),
        };
    }

    private function addModules(string $type, array $modules, array &$modulesData): void
    {
        foreach ($modules as $module) {
            $key = $this->getModule($module['name']);

            $modulesData[$key] = $this->modules[$key];
            $modulesData[$key]['key'] = $key;
            $modulesData[$key]['type'] = $type;
        }

        /*
"bouclierAmrita": {
  "description": "",
  "noyaux": 4,
  "activation": "Deplacement",
  "duree": "",
  "bonus": {
    "resilience": 4,
    "defense": 4,
    "reaction": 4,
    "champDeForce": 4
  },
  "key": "bouclierAmrita",
  "type": "base"
}
         */
    }
}
