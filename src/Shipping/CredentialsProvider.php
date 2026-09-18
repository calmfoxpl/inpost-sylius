<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Shipping;

use Calmfox\InPostBundle\Core\SecretBox;
use Calmfox\InPostBundle\Repository\SettingsRepository;

/**
 * Dane konta dla trybu: to, co operator zapisał w panelu, wygrywa z konfiguracją sklepu;
 * konfiguracja (zwykle zmienne środowiskowe) zostaje zapasem i drogą dla wdrożeń, które wolą
 * trzymać sekrety poza bazą. Każde pole rozstrzygamy osobno — można mieć ID organizacji
 * z konfiguracji, a token z panelu.
 */
final class CredentialsProvider
{
    /**
     * @param array{token: string, organization_id: string} $production dane z konfiguracji sklepu
     * @param array{token: string, organization_id: string} $sandbox
     */
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretBox $secretBox,
        private readonly array $production,
        private readonly array $sandbox,
    ) {
    }

    public function get(bool $sandbox): Credentials
    {
        $config = $sandbox ? $this->sandbox : $this->production;

        try {
            $stored = $this->settings->findSettings();
        } catch (\Throwable) {
            $stored = null;
        }

        $panelToken = $this->secretBox->decrypt($stored?->getEncryptedToken($sandbox)) ?? '';
        $panelOrganization = trim((string) $stored?->getOrganizationId($sandbox));
        $configToken = trim($config['token']);
        $configOrganization = trim($config['organization_id']);

        return new Credentials(
            '' !== $panelToken ? $panelToken : $configToken,
            '' !== $panelOrganization ? $panelOrganization : $configOrganization,
            '' !== $panelToken ? Credentials::SOURCE_PANEL : ('' !== $configToken ? Credentials::SOURCE_CONFIG : Credentials::SOURCE_NONE),
            '' !== $panelOrganization ? Credentials::SOURCE_PANEL : ('' !== $configOrganization ? Credentials::SOURCE_CONFIG : Credentials::SOURCE_NONE),
        );
    }
}
