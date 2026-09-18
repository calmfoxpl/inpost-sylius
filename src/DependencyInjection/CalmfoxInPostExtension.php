<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class CalmfoxInPostExtension extends Extension implements PrependExtensionInterface
{
    public function getAlias(): string
    {
        return 'calmfox_inpost';
    }

    /** @param array<array-key, mixed> $configs */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        foreach (['api_token', 'organization_id', 'sandbox_api_token', 'sandbox_organization_id', 'sandbox', 'methods', 'sending_method', 'locker_template', 'courier_parcel', 'cod_payment_methods', 'points_cache_ttl'] as $key) {
            $container->setParameter('calmfox_inpost.'.$key, $config[$key]);
        }
        $container->setParameter('calmfox_inpost.insurance.mode', $config['insurance']['mode']);
        $container->setParameter('calmfox_inpost.insurance.max_amount', $config['insurance']['max_amount']);

        (new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2).'/config')))->load('services.php');
    }

    /**
     * Sklep po instalacji nie edytuje nic poza tras i danymi konta: mapowanie encji i miejsca
     * w szablonach Syliusa dopinamy sami.
     */
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine', ['orm' => ['mappings' => [
            'CalmfoxInPost' => [
                'type' => 'attribute',
                'dir' => \dirname(__DIR__).'/Entity',
                'prefix' => 'Calmfox\InPostBundle\Entity',
                'is_bundle' => false,
            ],
        ]]]);

        $bundles = (array) $container->getParameter('kernel.bundles');
        if (!isset($bundles['SyliusTwigHooksBundle'])) {
            return;
        }

        $container->prependExtensionConfig('sylius_twig_hooks', ['hooks' => [
            // Pod listą metod dostawy danej przesyłki (nagłówek 100, wybór 0).
            'sylius_shop.checkout.select_shipping.content.form.shipments.shipment' => [
                'calmfox_inpost_point' => ['template' => '@CalmfoxInPost/shop/point_picker.html.twig', 'priority' => -100],
            ],
            // Pod tabelą przesyłek w zamówieniu (nagłówek 100, pozycje 0).
            'sylius_admin.order.show.content.sections.shipments' => [
                'calmfox_inpost' => ['template' => '@CalmfoxInPost/admin/shipments.html.twig', 'priority' => -100],
            ],
        ]]);
    }
}
