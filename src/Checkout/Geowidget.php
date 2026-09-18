<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Checkout;

use Calmfox\InPostBundle\Core\Links;
use Calmfox\InPostBundle\Repository\SettingsRepository;
use Calmfox\InPostBundle\Shipping\Environment;

/**
 * Mapa paczkomatów — oficjalny Geowidget v5 InPostu. Token wydaje Manager Paczek na konkretną
 * domenę, osobno w produkcji i w sandboxie, a każde środowisko ma własny host widgetu; token
 * z jednego nie działa w drugim. Dlatego mapa idzie za trybem sklepu, tak samo jak nadawanie.
 *
 * Bez tokenu dla bieżącego trybu mapy po prostu nie ma — zostaje lista najbliższych punktów.
 */
final class Geowidget
{
    /** @param array{production: string, sandbox: string} $configTokens */
    public function __construct(
        private readonly Environment $environment,
        private readonly SettingsRepository $settings,
        private readonly array $configTokens,
        private readonly string $config,
    ) {
    }

    /** @return array{token: string, source: string} źródło: panel | config | none */
    public function token(bool $sandbox): array
    {
        try {
            $panel = trim((string) $this->settings->findSettings()?->getGeowidgetToken($sandbox));
        } catch (\Throwable) {
            $panel = '';
        }
        if ('' !== $panel) {
            return ['token' => $panel, 'source' => 'panel'];
        }

        $config = trim($this->configTokens[$sandbox ? 'sandbox' : 'production']);

        return ['token' => $config, 'source' => '' !== $config ? 'config' : 'none'];
    }

    /** @return array{token: string, script: string, stylesheet: string, config: string}|null null = bez mapy */
    public function forCheckout(): ?array
    {
        $sandbox = $this->environment->isSandbox();
        $token = $this->token($sandbox)['token'];
        if ('' === $token) {
            return null;
        }

        $host = Links::geowidget($sandbox);

        return [
            'token' => $token,
            'script' => $host.'/inpost-geowidget.js',
            'stylesheet' => $host.'/inpost-geowidget.css',
            'config' => $this->config,
        ];
    }
}
