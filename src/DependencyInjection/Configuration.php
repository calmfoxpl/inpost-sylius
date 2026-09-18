<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\DependencyInjection;

use Calmfox\InPostBundle\Core\InsurancePolicy;
use Calmfox\InPostBundle\Core\Parcel;
use Calmfox\InPostBundle\Core\Service;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('calmfox_inpost');

        $tree->getRootNode()
            ->children()
                ->scalarNode('api_token')->defaultValue('')->info('Token ShipX (Manager Paczek → Moje konto → API). Trzymaj w zmiennej środowiskowej.')->end()
                ->scalarNode('organization_id')->defaultValue('')->info('Identyfikator organizacji ShipX.')->end()
                ->scalarNode('sandbox_api_token')->defaultValue('')->info('Token ShipX konta SANDBOX (sandbox-manager.paczkomaty.pl) — osobny od produkcyjnego.')->end()
                ->scalarNode('sandbox_organization_id')->defaultValue('')->info('Identyfikator organizacji konta sandbox.')->end()
                ->booleanNode('sandbox')->defaultFalse()->info('Tryb startowy. Obowiązuje, dopóki operator nie przełączy trybu w panelu (Konfiguracja → InPost).')->end()
                ->arrayNode('methods')
                    ->info('Kod metody dostawy Syliusa → usługa ShipX. Metody spoza mapy paczka ignoruje.')
                    ->useAttributeAsKey('code')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue(['inpost_point' => Service::LOCKER_STANDARD, 'inpost' => Service::COURIER_STANDARD])
                ->end()
                ->enumNode('sending_method')
                    ->values(['dispatch_order', 'parcel_locker', 'pop', 'any_point', 'branch'])
                    ->defaultValue('dispatch_order')
                    ->info('Jak paczka trafia do InPostu: dispatch_order = odbiera kurier, parcel_locker = nadajesz w paczkomacie.')
                ->end()
                ->enumNode('locker_template')->values(Parcel::TEMPLATES)->defaultValue('small')->info('Gabaryt podpowiadany przy nadaniu do paczkomatu.')->end()
                ->arrayNode('courier_parcel')
                    ->info('Wymiary (mm) i waga (kg) podpowiadane przy nadaniu kurierem.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('length')->min(1)->defaultValue(400)->end()
                        ->integerNode('width')->min(1)->defaultValue(300)->end()
                        ->integerNode('height')->min(1)->defaultValue(150)->end()
                        ->floatNode('weight')->min(0.01)->defaultValue(2.0)->end()
                    ->end()
                ->end()
                ->arrayNode('insurance')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('mode')->values([InsurancePolicy::MODE_ORDER_TOTAL, InsurancePolicy::MODE_NONE])->defaultValue(InsurancePolicy::MODE_ORDER_TOTAL)->end()
                        ->integerNode('max_amount')->min(1)->defaultNull()->info('Górny limit ubezpieczenia w groszach; null = bez limitu po stronie sklepu.')->end()
                    ->end()
                ->end()
                ->arrayNode('cod_payment_methods')
                    ->info('Kody metod płatności, które oznaczają pobranie.')
                    ->scalarPrototype()->end()
                    ->defaultValue(['cash_on_delivery'])
                ->end()
                ->integerNode('points_cache_ttl')->min(0)->defaultValue(900)->end()
            ->end();

        return $tree;
    }
}
