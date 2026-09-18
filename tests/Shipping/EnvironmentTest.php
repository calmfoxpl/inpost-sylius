<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Tests\Shipping;

use Calmfox\InPostBundle\Entity\Settings;
use Calmfox\InPostBundle\Repository\SettingsRepository;
use Calmfox\InPostBundle\Shipping\Environment;
use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository::class)) {
            self::markTestSkipped('Wymaga doctrine/doctrine-bundle.');
        }
    }

    public function testConfigDefaultAppliesUntilTheOperatorChoosesAMode(): void
    {
        $repository = $this->createStub(SettingsRepository::class);
        $repository->method('findSettings')->willReturn(null);

        self::assertTrue((new Environment($repository, true))->isSandbox());
        self::assertFalse((new Environment($repository, false))->isSandbox());
    }

    public function testPanelChoiceWinsOverConfig(): void
    {
        $repository = $this->createStub(SettingsRepository::class);
        $repository->method('findSettings')->willReturn(new Settings(true));

        self::assertTrue((new Environment($repository, false))->isSandbox());
    }

    public function testMissingTableFallsBackToConfigInsteadOfBreakingTheShop(): void
    {
        $repository = $this->createStub(SettingsRepository::class);
        $repository->method('findSettings')->willThrowException(new \RuntimeException('Table calmfox_inpost_settings does not exist'));

        self::assertFalse((new Environment($repository, false))->isSandbox());
    }

    public function testSwitchIsVisibleImmediately(): void
    {
        $repository = $this->createMock(SettingsRepository::class);
        $repository->method('findSettings')->willReturn(null);
        $repository->expects(self::once())->method('saveSandbox')->with(true)->willReturn(new Settings(true));

        $environment = new Environment($repository, false);
        self::assertFalse($environment->isSandbox());
        $environment->switchTo(true);
        self::assertTrue($environment->isSandbox());
    }
}
