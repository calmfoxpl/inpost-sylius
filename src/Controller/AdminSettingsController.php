<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Controller;

use Calmfox\InPostBundle\Api\ShipXClients;
use Calmfox\InPostBundle\Api\ShipXException;
use Calmfox\InPostBundle\CalmfoxInPostBundle;
use Calmfox\InPostBundle\Core\Links;
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
 * Ekran „InPost" w konfiguracji panelu: przełącznik trybu (produkcja / sandbox), stan obu
 * kompletów danych konta z testem połączenia i odnośniki, po które operator sięga przy pracy.
 * Tokenów tu się nie wpisuje ani nie ogląda — ekran mówi tylko, czy są.
 */
final class AdminSettingsController
{
    public const CSRF_ID = 'calmfox_inpost_settings';

    public function __construct(
        private readonly Twig $twig,
        private readonly Environment $environment,
        private readonly ShipXClients $clients,
        private readonly MethodMap $methodMap,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function index(): Response
    {
        return new Response($this->twig->render('@CalmfoxInPost/admin/settings.html.twig', [
            'sandbox' => $this->environment->isSandbox(),
            'environments' => [
                ['sandbox' => false, 'configured' => $this->clients->get(false)->isConfigured(), 'manager' => Links::manager(false)],
                ['sandbox' => true, 'configured' => $this->clients->get(true)->isConfigured(), 'manager' => Links::manager(true)],
            ],
            'methods' => $this->methodMap->all(),
            'links' => Links::forOperator(),
            'version' => CalmfoxInPostBundle::VERSION,
            'csrf_id' => self::CSRF_ID,
        ]));
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
