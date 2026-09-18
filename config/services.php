<?php

declare(strict_types=1);

use Calmfox\InPostBundle\Api\PointsClient;
use Calmfox\InPostBundle\Api\ShipXClients;
use Calmfox\InPostBundle\Checkout\ShipmentTypeExtension;
use Calmfox\InPostBundle\Command\SyncCommand;
use Calmfox\InPostBundle\Controller\AdminSettingsController;
use Calmfox\InPostBundle\Controller\AdminShipmentController;
use Calmfox\InPostBundle\Controller\PointSearchController;
use Calmfox\InPostBundle\Core\InsurancePolicy;
use Calmfox\InPostBundle\Core\SecretBox;
use Calmfox\InPostBundle\Menu\AdminMenuListener;
use Calmfox\InPostBundle\Repository\InPostShipmentRepository;
use Calmfox\InPostBundle\Repository\SettingsRepository;
use Calmfox\InPostBundle\Shipping\CredentialsProvider;
use Calmfox\InPostBundle\Shipping\Dispatcher;
use Calmfox\InPostBundle\Shipping\Environment;
use Calmfox\InPostBundle\Shipping\MethodMap;
use Calmfox\InPostBundle\Shipping\ShipmentRequestFactory;
use Calmfox\InPostBundle\Twig\InPostExtension;
use Calmfox\InPostBundle\Validator\PointSelectedValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->private();

    $services->set(SettingsRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');

    $services->set(SecretBox::class)->args([param('kernel.secret')]);

    $services->set(CredentialsProvider::class)->args([
        service(SettingsRepository::class),
        service(SecretBox::class),
        ['token' => param('calmfox_inpost.api_token'), 'organization_id' => param('calmfox_inpost.organization_id')],
        ['token' => param('calmfox_inpost.sandbox_api_token'), 'organization_id' => param('calmfox_inpost.sandbox_organization_id')],
    ]);

    $services->set(ShipXClients::class)->args([service('http_client'), service(CredentialsProvider::class)]);

    $services->set(Environment::class)->args([
        service(SettingsRepository::class),
        param('calmfox_inpost.sandbox'),
    ]);

    $services->set(PointsClient::class)->args([
        service('http_client'),
        service('cache.app')->nullOnInvalid(),
        param('calmfox_inpost.points_cache_ttl'),
    ]);

    $services->set(MethodMap::class)->args([param('calmfox_inpost.methods')]);

    $services->set(InsurancePolicy::class)->args([
        param('calmfox_inpost.insurance.mode'),
        param('calmfox_inpost.insurance.max_amount'),
    ]);

    $services->set(InPostShipmentRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');

    $services->set(ShipmentRequestFactory::class)->args([
        service(InsurancePolicy::class),
        param('calmfox_inpost.cod_payment_methods'),
        param('calmfox_inpost.sending_method'),
    ]);

    $services->set(Dispatcher::class)->args([
        service(ShipXClients::class),
        service(Environment::class),
        service(ShipmentRequestFactory::class),
        service('doctrine.orm.entity_manager'),
    ]);

    $services->set(ShipmentTypeExtension::class)
        ->args([
            service(MethodMap::class),
            service(InPostShipmentRepository::class),
            service('doctrine.orm.entity_manager'),
            service(PointsClient::class),
            service('translator'),
        ])
        ->tag('form.type_extension');

    $services->set(PointSelectedValidator::class)
        ->args([service(MethodMap::class), service(InPostShipmentRepository::class)])
        ->tag('validator.constraint_validator');

    $services->set(PointSearchController::class)
        ->args([service(PointsClient::class)])
        ->public()
        ->tag('controller.service_arguments');

    $services->set(AdminShipmentController::class)
        ->args([
            service(InPostShipmentRepository::class),
            service(Dispatcher::class),
            service('security.csrf.token_manager'),
            service('router'),
            service('translator'),
        ])
        ->public()
        ->tag('controller.service_arguments');

    $services->set(AdminSettingsController::class)
        ->args([
            service('twig'),
            service(Environment::class),
            service(ShipXClients::class),
            service(MethodMap::class),
            service(CredentialsProvider::class),
            service(SettingsRepository::class),
            service(SecretBox::class),
            service('security.csrf.token_manager'),
            service('router'),
            service('translator'),
        ])
        ->public()
        ->tag('controller.service_arguments');

    $services->set(AdminMenuListener::class)
        ->tag('kernel.event_listener', ['event' => 'sylius.menu.admin.main', 'method' => '__invoke']);

    $services->set(SyncCommand::class)
        ->args([service(InPostShipmentRepository::class), service(Dispatcher::class)])
        ->tag('console.command');

    $services->set(InPostExtension::class)
        ->args([
            service(MethodMap::class),
            service(InPostShipmentRepository::class),
            service(ShipmentRequestFactory::class),
            service(InsurancePolicy::class),
            service(ShipXClients::class),
            service(Environment::class),
            param('calmfox_inpost.locker_template'),
            param('calmfox_inpost.courier_parcel'),
        ])
        ->tag('twig.extension');
};
