<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:compendium:weapons',
    description: 'Génération du compendium des armes',
)]
class CompendiumWeaponsCommand extends AbstractCompendiumCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $items = [];

        foreach ($this->api->get('weapon') as $data) {
            $apiData = $this->api->get('weapon/'.$data['id']);
            if ($this->ignoreWeapon($apiData['slug'])) {
                continue;
            }

            $nbAttacks = \count($apiData['attacks']);
            $itemData = $this->getBaseData();
            $itemData['name'] = $apiData['name'];
            $itemData['img'] = $this->getImg($apiData['slug']) ?? $itemData['img'];
            $itemData['system']['description'] = $this->cleanDescription($apiData['description']);
            $itemData['system']['type'] = $this->getWeaponType($apiData['category']['name']);
            $itemData['system']['prix'] = $apiData['cost'];

            // Les améliorations sont à acheter, donc pas dans le compendium de base...
            // $this->addEnhancements($apiData['enhancements'], $itemData);

            foreach ($apiData['attacks'] as $attack) {
                $subItemData = $itemData;

                if (1 < $nbAttacks) {
                    $subItemData['name'] .= ' - '.$attack['name'];
                }

                $subItemData['_id'] = $this->generateId($subItemData['name']);
                $subItemData['system']['portee'] = $this->getReach($attack['reach']);
                $subItemData['system']['degats']['dice'] = $attack['damage_dice'];
                $subItemData['system']['degats']['fixe'] = $attack['damage_bonus'];
                $subItemData['system']['violence']['dice'] = $attack['violence_dice'];
                $subItemData['system']['violence']['fixe'] = $attack['violence_bonus'];

                $this->addEffects($attack['effects'], $subItemData);

                $items[] = $this->serializeData($subItemData);
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
        return 'weapon';
    }

    private function ignoreWeapon(string $slug): bool
    {
        // Les grenades sont déjà incluses sur les fiches des Chevaliers
        return 'grenade-intelligente' === $slug;
    }

    private function addEffects(array $effects, array &$itemData): void
    {
        foreach ($effects as $effect) {
            // Zakarik : "Le Ignore couvert, ce n'est pas un effet des livres, c'est un effet propre à Knight JDR système, c'est pour ça qu'il n'est pas dans la liste"
            if ($this->ignoreEffect($effect['effect']['name'])) {
                continue;
            }

            $value = $this->getEffect($effect['effect']['slug']);

            if (0 < $effect['effect_level']) {
                $value .= ' '.$effect['effect_level'];
            }

            $itemData['system']['effets']['raw'][] = $value;
        }
    }

    private function addEnhancements(array $enhancements, array &$itemData): void
    {
        foreach ($enhancements as $enhancement) {
            $data = $this->getEnhancementData($enhancement['slug']);

            // Cas particulier du jumelage
            if ('jumelage' === $data['value']) {
                foreach (['jumelageakimbo', 'jumelageambidextrie'] as $name) {
                    $itemData['system'][$data['group']]['raw'][] = str_replace(
                        ' ',
                        '<space>',
                        sprintf('%s (%s)', $name, $itemData['name'])
                    );
                }
            } else {
                $itemData['system'][$data['group']]['raw'][] = $data['value'];
            }
        }
    }

    private function ignoreEffect(string $value): bool
    {
        $value = str_replace(' X', '', $value);

        return str_starts_with($value, '[') && str_ends_with($value, ']');
    }

    private function getEffect(string $value): string
    {
        return match ($value) {
            'anti-anatheme' => 'antianatheme',
            'anti-vehicule' => 'antivehicule',
            'artillerie' => 'artillerie',
            'assassin-x' => 'assassin',
            'assistance-a-lattaque' => 'assistanceattaque',
            'barrage-x' => 'barrage',
            'briser-la-resilience' => 'briserlaresilience',
            'cadence-x' => 'cadence',
            'chargeur-x' => 'chargeur',
            'choc-x' => 'choc',
            'defense-x' => 'defense',
            'degats-continus-x' => 'degatscontinus',
            'demoralisant' => 'demoralisant',
            'designation' => 'designation',
            'destructeur' => 'destructeur',
            'deux-mains' => 'deuxmains',
            'dispersion-x' => 'dispersion',
            'en-chaine' => 'enchaine',
            'esperance' => 'esperance',
            'fureur' => 'fureur',
            'ignore-armure' => 'ignorearmure',
            'ignore-cdf' => 'ignorechampdeforce',
            'jumele-akimbo' => 'jumeleakimbo',
            'jumele-ambidextrie' => 'jumeleambidextrie',
            'leste' => 'leste',
            'lourd' => 'lourd',
            'lumiere-x' => 'lumiere',
            'meurtrier' => 'meurtrier',
            'obliteration' => 'obliteration',
            'orfevrerie' => 'orfevrerie',
            'parasitage-x' => 'parasitage',
            'penetrant-x' => 'penetrant',
            'perce-armure-x' => 'percearmure',
            'precision' => 'precision',
            'reaction-x' => 'reaction',
            'silencieux' => 'silencieux',
            'soumission' => 'soumission',
            'tenebricide' => 'tenebricide',
            'tir-en-rafale' => 'tirenrafale',
            'tir-en-securite' => 'tirensecurite',
            'ultraviolence' => 'ultraviolence',
            default => throw new \InvalidArgumentException(sprintf('Effet "%s" invalide', $value)),
        };
    }

    private function getEnhancementData(string $value): array
    {
        static $enhancements = [
            'distance'      => [
                'canon-long'                       => 'canonlong',
                'canon-raccourci'                  => 'canonraccourci',
                'chambre-double'                   => 'chambredouble',
                'chargeur-et-balles-grappes'       => 'chargeurballesgrappes',
                'chargeur-et-munitions-explosives' => 'chargeurmunitionsexplosives',
                'interface-de-guidage'             => 'interfaceguidage',
                'jumelage'                         => 'jumelage',
                'lunette-intelligente'             => 'lunetteintelligente',
                'munitions-drones'                 => 'munitionsdrones',
                'munitions-hyper-velocite'         => 'munitionshypervelocite',
                'munitions-iem'                    => 'munitionsiem',
                'munitions-non-letales'            => 'munitionsnonletales',
                'munitions-subsoniques'            => 'munitionssubsoniques',
                'pointeur-laser'                   => 'pointeurlaser',
                'protection-darme'                 => 'protectionarme',
                'revetement-omega'                 => 'revetementomega',
                'structure-alpha'                  => 'structurealpha',
                'systeme-de-refroidissement'       => 'systemerefroidissement',
            ],
            'ornementales'  => [
                'arabesques-iridescentes'                    => 'arabesqueiridescentes',
                'arme-azurine'                               => 'armeazurine',
                'arme-rouge-sang'                            => 'armerougesang',
                'armure-gravee'                              => 'armuregravee',
                'blason-du-chevalier'                        => 'blasonchevalier',
                'bouclier-grave'                             => 'boucliergrave',
                'chene-sculpte'                              => 'chenesculpte',
                'chromee-avec-lignes-lumineuses-et-colorees' => 'chromeligneslumineuses',
                'code-du-knight-grave'                       => 'codeknightgrave',
                'crane-rieur-grave'                          => 'cranerieurgrave',
                'faucheuse-gravee'                           => 'faucheusegravee',
                'faucon-et-plumes-luminescentes'             => 'fauconplumesluminescentes',
                'flammes-stylisees'                          => 'flammesstylisees',
                'griffures-gravees'                          => 'griffuresgravees',
                'masque-brise-sculpte'                       => 'masquebrisesculpte',
                'rouages-casses-graves'                      => 'rouagescassesgraves',
                'sillons-formant-des-lignes-et-des-fleches'  => 'sillonslignesfleches',
            ],
            'structurelles' => [
                'agressive'      => 'agressive',
                'allegee'        => 'allegee',
                'assassine'      => 'assassine',
                'barbelee'       => 'barbelee',
                'connectee'      => 'connectee',
                'electrifiee'    => 'electrifiee',
                'indestructible' => 'indestructible',
                'jumelle'        => 'jumelle',
                'lumineuse'      => 'lumineuse',
                'massive'        => 'massive',
                'protectrice'    => 'protectrice',
                'sur'            => 'soeur',
                'sournoise'      => 'sournoise',
                'sur-mesure'     => 'surmesure',
            ],
        ];

        foreach ($enhancements as $group => $values) {
            foreach ($values as $apiSlug => $systemValue) {
                if ($apiSlug === $value) {
                    return [
                        'group' => $group,
                        'value' => $systemValue,
                    ];
                }
            }
        }

        throw new \InvalidArgumentException(sprintf('Amélioration "%s" invalide', $value));
    }
}
