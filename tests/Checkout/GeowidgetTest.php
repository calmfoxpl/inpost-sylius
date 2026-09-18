<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Tests\Checkout;

use Calmfox\InPostBundle\Checkout\Geowidget;
use Calmfox\InPostBundle\Entity\Settings;
use Calmfox\InPostBundle\Repository\SettingsRepository;
use Calmfox\InPostBundle\Shipping\Environment;
use PHPUnit\Framework\TestCase;

final class GeowidgetTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository::class)) {
            self::markTestSkipped('Wymaga doctrine/doctrine-bundle.');
        }
    }

    public function testNoTokenForTheCurrentModeMeansNoMap(): void
    {
        $settings = new Settings(true);
        $settings->setGeowidgetToken(false, 'prod.map.token');

        self::assertNull($this->geowidget($settings)->forCheckout(), 'sklep jest w sandboxie, a token jest tylko produkcyjny');
    }

    public function testSandboxModeUsesSandboxHostAndToken(): void
    {
        $settings = new Settings(true);
        $settings->setGeowidgetToken(true, 'sandbox.map.token');

        $map = $this->geowidget($settings)->forCheckout();

        self::assertNotNull($map);
        self::assertSame('sandbox.map.token', $map['token']);
        self::assertSame('https://sandbox-easy-geowidget-sdk.easypack24.net/inpost-geowidget.js', $map['script']);
        self::assertSame('parcelcollect', $map['config']);
    }

    public function testProductionModeUsesProductionHostAndPanelWinsOverConfig(): void
    {
        $settings = new Settings(false);
        $settings->setGeowidgetToken(false, 'panel.map.token');

        $geowidget = $this->geowidget($settings, ['production' => 'config.map.token', 'sandbox' => '']);

        self::assertSame('https://geowidget.inpost.pl/inpost-geowidget.js', $geowidget->forCheckout()['script'] ?? null);
        self::assertSame(['token' => 'panel.map.token', 'source' => 'panel'], $geowidget->token(false));
        self::assertSame(['token' => '', 'source' => 'none'], $geowidget->token(true));
    }

    public function testConfigTokenIsTheFallback(): void
    {
        $geowidget = $this->geowidget(null, ['production' => 'config.map.token', 'sandbox' => '']);

        self::assertSame(['token' => 'config.map.token', 'source' => 'config'], $geowidget->token(false));
    }

    /** @param array{production: string, sandbox: string} $config */
    private function geowidget(?Settings $settings, array $config = ['production' => '', 'sandbox' => '']): Geowidget
    {
        $repository = $this->createStub(SettingsRepository::class);
        $repository->method('findSettings')->willReturn($settings);

        return new Geowidget(new Environment($repository, false), $repository, $config, 'parcelcollect');
    }
}
