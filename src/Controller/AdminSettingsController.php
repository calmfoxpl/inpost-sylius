<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Controller;

use Calmfox\InPostBundle\Api\ShipXClients;
use Calmfox\InPostBundle\Api\ShipXException;
use Calmfox\InPostBundle\CalmfoxInPostBundle;
use Calmfox\InPostBundle\Checkout\Geowidget;
use Calmfox\InPostBundle\Core\Links;
use Calmfox\InPostBundle\Core\SecretBox;
use Calmfox\InPostBundle\Repository\SettingsRepository;
use Calmfox\InPostBundle\Shipping\CredentialsProvider;
use Calmfox\InPostBundle\Shipping\Environment;
use Calmfox\InPostBundle\Shipping\MethodMap;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig;

/**
 * Ekran „InPost" w konfiguracji panelu: przełącznik trybu (produkcja / sandbox), dane obu kont
 * z testem połączenia i odnośniki, po które operator sięga przy pracy.
 *
 * Token jest polem JEDNOSTRONNYM: da się go zapisać, podmienić albo usunąć, ale żadna odpowiedź
 * tego kontrolera go nie zawiera — ani w HTML-u, ani w komunikatach. Ekran mówi tylko, czy token
 * jest i skąd pochodzi (panel / konfiguracja sklepu).
 */
final class AdminSettingsController
{
    public const CSRF_ID = 'calmfox_inpost_settings';

    public function __construct(
        private readonly Twig $twig,
        private readonly Environment $environment,
        private readonly ShipXClients $clients,
        private readonly MethodMap $methodMap,
        private readonly CredentialsProvider $credentials,
        private readonly SettingsRepository $settings,
        private readonly SecretBox $secretBox,
        private readonly Geowidget $geowidget,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function index(): Response
    {
        return new Response($this->twig->render('@CalmfoxInPost/admin/settings.html.twig', [
            'sandbox' => $this->environment->isSandbox(),
            'environments' => [$this->describe(false), $this->describe(true)],
            'methods' => $this->methodMap->all(),
            'links' => Links::forOperator(),
            'version' => CalmfoxInPostBundle::VERSION,
            'csrf_id' => self::CSRF_ID,
        ]));
    }

    public function saveCredentials(Request $request): Response
    {
        $this->assertCsrf($request);
        $sandbox = '1' === (string) $request->request->get('sandbox');

        $token = trim((string) $request->request->get('api_token', ''));
        $settings = $this->settings->getOrCreate($this->environment->isSandbox());

        if ('1' === (string) $request->request->get('remove_token')) {
            $settings->setEncryptedToken($sandbox, null);
        } elseif ('' !== $token) {
            // Token ShipX to JWT: trzy człony base64url. Wklejony z obciętym końcem i tak by nie
            // zadziałał (tak skończyła druga bramka w poprzedniej integracji) — lepiej powiedzieć od razu.
            if (1 !== preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $token)) {
                $this->flash($request, 'error', $this->translator->trans('calmfox_inpost.settings.flash.token_malformed'));

                return $this->back();
            }
            $settings->setEncryptedToken($sandbox, $this->secretBox->encrypt($token));
        }
        // Puste pole tokenu = zostaw zapisany bez zmian.

        $organizationId = trim((string) $request->request->get('organization_id', ''));
        if ('' !== $organizationId && 1 !== preg_match('/^\d{1,32}$/', $organizationId)) {
            $this->flash($request, 'error', $this->translator->trans('calmfox_inpost.settings.flash.organization_malformed'));

            return $this->back();
        }
        $settings->setOrganizationId($sandbox, $organizationId);

        // Token mapy jest publiczny (trafia do HTML-a koszyka), więc pole jest zwykłe: widać go i puste = usuń.
        $geowidgetToken = trim((string) $request->request->get('geowidget_token', ''));
        if ('' !== $geowidgetToken && 1 !== preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $geowidgetToken)) {
            $this->flash($request, 'error', $this->translator->trans('calmfox_inpost.settings.flash.geowidget_malformed'));

            return $this->back();
        }
        $settings->setGeowidgetToken($sandbox, $geowidgetToken);

        $this->settings->flush();
        $this->flash($request, 'success', $this->translator->trans('calmfox_inpost.settings.flash.credentials_saved'));

        return $this->back();
    }

    public function switchMode(Request $request): Response
    {
        $this->assertCsrf($request);

        $sandbox = '1' === (string) $request->request->get('sandbox');
        if (!$this->clients->get($sandbox)->isConfigured()) {
            $this->flash($request, 'error', $this->translator->trans($sandbox ? 'calmfox_inpost.settings.flash.sandbox_not_configured' : 'calmfox_inpost.settings.flash.production_not_configured'));

            return $this->back();
        }

        $this->environment->switchTo($sandbox);
        $this->flash($request, 'success', $this->translator->trans($sandbox ? 'calmfox_inpost.settings.flash.switched_sandbox' : 'calmfox_inpost.settings.flash.switched_production'));

        return $this->back();
    }

    public function test(Request $request): Response
    {
        $this->assertCsrf($request);
        $sandbox = '1' === (string) $request->request->get('sandbox');

        try {
            $organization = $this->clients->get($sandbox)->getOrganization();
            $services = \is_array($organization['services'] ?? null) ? array_filter($organization['services'], 'is_string') : [];
            $missing = array_values(array_diff(array_unique(array_values($this->methodMap->all())), $services));

            $this->flash($request, [] === $missing ? 'success' : 'error', $this->translator->trans(
                [] === $missing ? 'calmfox_inpost.settings.flash.test_ok' : 'calmfox_inpost.settings.flash.test_missing_services',
                ['%name%' => \is_string($organization['name'] ?? null) ? $organization['name'] : '—', '%services%' => implode(', ', $missing)],
            ));
        } catch (ShipXException $e) {
            $this->flash($request, 'error', $e->getOperatorMessage());
        }

        return $this->back();
    }

    /** @return array<string, mixed> opis konta dla szablonu — bez tokenu */
    private function describe(bool $sandbox): array
    {
        $credentials = $this->credentials->get($sandbox);
        $stored = null;
        try {
            $stored = $this->settings->findSettings();
        } catch (\Throwable) {
        }

        return [
            'sandbox' => $sandbox,
            'configured' => $credentials->isComplete(),
            'tokenSource' => $credentials->tokenSource,
            'organizationSource' => $credentials->organizationSource,
            'organizationId' => $credentials->organizationId,
            'panelOrganizationId' => (string) $stored?->getOrganizationId($sandbox),
            'hasPanelToken' => null !== $stored?->getEncryptedToken($sandbox),
            'panelGeowidgetToken' => (string) $stored?->getGeowidgetToken($sandbox),
            'geowidgetSource' => $this->geowidget->token($sandbox)['source'],
            'manager' => Links::manager($sandbox),
        ];
    }

    private function assertCsrf(Request $request): void
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_ID, (string) $request->request->get('_csrf_token')))) {
            throw new AccessDeniedHttpException('Nieprawidłowy token CSRF.');
        }
    }

    private function flash(Request $request, string $type, string $message): void
    {
        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $message);
        }
    }

    private function back(): RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->generate('calmfox_inpost_admin_settings'));
    }
}
