<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Tests\Shipping;

use Calmfox\InPostBundle\Core\SecretBox;
use Calmfox\InPostBundle\Entity\Settings;
use Calmfox\InPostBundle\Repository\SettingsRepository;
use Calmfox\InPostBundle\Shipping\Credentials;
use Calmfox\InPostBundle\Shipping\CredentialsProvider;
use PHPUnit\Framework\TestCase;

final class CredentialsProviderTest extends TestCase
{
    private SecretBox $box;

    protected function setUp(): void
    {
        if (!class_exists(\Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository::class)) {
            self::markTestSkipped('Wymaga doctrine/doctrine-bundle.');
        }
        $this->box = new SecretBox('app-secret');
    }

    public function testConfigIsUsedWhenThePanelHasNothing(): void
    {
        $credentials = $this->provider(null)->get(false);

        self::assertSame('config-token', $credentials->token);
        self::assertSame('100', $credentials->organizationId);
        self::assertSame(Credentials::SOURCE_CONFIG, $credentials->tokenSource);
        self::assertTrue($credentials->isComplete());
    }

    public function testPanelWinsFieldByField(): void
    {
        $settings = new Settings(false);
        $settings->setEncryptedToken(false, $this->box->encrypt('panel-token'));

        $credentials = $this->provider($settings)->get(false);

        self::assertSame('panel-token', $credentials->token);
        self::assertSame(Credentials::SOURCE_PANEL, $credentials->tokenSource);
        self::assertSame('100', $credentials->organizationId, 'ID organizacji zostaje z konfiguracji');
        self::assertSame(Credentials::SOURCE_CONFIG, $credentials->organizationSource);
    }

    public function testProductionAndSandboxAreSeparateAccounts(): void
    {
        $settings = new Settings(false);
        $settings->setEncryptedToken(true, $this->box->encrypt('sandbox-panel-token'));
        $settings->setOrganizationId(true, ' 777 ');

        $provider = $this->provider($settings);

        self::assertSame('sandbox-panel-token', $provider->get(true)->token);
        self::assertSame('777', $provider->get(true)->organizationId);
        self::assertSame('config-token', $provider->get(false)->token);
    }

    public function testTokenEncryptedWithAnotherSecretCountsAsMissing(): void
    {
        $settings = new Settings(false);
        $settings->setEncryptedToken(true, (new SecretBox('previous-secret'))->encrypt('lost'));

        $credentials = $this->provider($settings)->get(true);

        self::assertSame('', $credentials->token);
        self::assertSame(Credentials::SOURCE_NONE, $credentials->tokenSource);
        self::assertFalse($credentials->isComplete());
    }

    private function provider(?Settings $settings): CredentialsProvider
    {
        $repository = $this->createStub(SettingsRepository::class);
        $repository->method('findSettings')->willReturn($settings);

        return new CredentialsProvider(
            $repository,
            $this->box,
            ['token' => 'config-token', 'organization_id' => '100'],
            ['token' => '', 'organization_id' => ''],
        );
    }
}
