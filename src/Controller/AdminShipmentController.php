<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Controller;

use Calmfox\InPostBundle\Api\ShipXException;
use Calmfox\InPostBundle\Core\InvalidShipmentException;
use Calmfox\InPostBundle\Core\Parcel;
use Calmfox\InPostBundle\Core\Service;
use Calmfox\InPostBundle\Entity\InPostShipment;
use Calmfox\InPostBundle\Repository\InPostShipmentRepository;
use Calmfox\InPostBundle\Shipping\Dispatcher;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Trzy akcje operatora przy zamówieniu: nadaj, odśwież status, pobierz etykietę. */
final class AdminShipmentController
{
    public function __construct(
        private readonly InPostShipmentRepository $repository,
        private readonly Dispatcher $dispatcher,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function dispatch(Request $request, int $id): Response
    {
        $shipment = $this->find($id);
        $this->assertCsrf($request, $shipment);

        try {
            $this->dispatcher->dispatch($shipment, $this->parcelFrom($request, $shipment), $this->insuranceFrom($request));
            $this->flash($request, 'success', null !== $shipment->getTrackingNumber()
                ? $this->translator->trans('calmfox_inpost.admin.flash.dispatched', ['%tracking%' => $shipment->getTrackingNumber()])
                : $this->translator->trans('calmfox_inpost.admin.flash.dispatched_pending'));
        } catch (ShipXException $e) {
            $this->flash($request, 'error', $e->getOperatorMessage());
        } catch (InvalidShipmentException|\InvalidArgumentException $e) {
            $this->flash($request, 'error', $e->getMessage());
        }

        return $this->backToOrder($shipment);
    }

    public function refresh(Request $request, int $id): Response
    {
        $shipment = $this->find($id);
        $this->assertCsrf($request, $shipment);

        try {
            $this->dispatcher->refresh($shipment);
            $this->flash($request, 'success', $this->translator->trans('calmfox_inpost.admin.flash.refreshed'));
        } catch (ShipXException $e) {
            $this->flash($request, 'error', $e->getOperatorMessage());
        }

        return $this->backToOrder($shipment);
    }

    public function label(Request $request, int $id): Response
    {
        $shipment = $this->find($id);

        try {
            $pdf = $this->dispatcher->label($shipment);
        } catch (ShipXException $e) {
            $this->flash($request, 'error', $e->getOperatorMessage());

            return $this->backToOrder($shipment);
        } catch (InvalidShipmentException $e) {
            $this->flash($request, 'error', $e->getMessage());

            return $this->backToOrder($shipment);
        }

        $filename = sprintf('inpost-%s.pdf', $shipment->getTrackingNumber() ?? $shipment->getShipxId());

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $filename),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public static function csrfTokenId(InPostShipment $shipment): string
    {
        return 'calmfox_inpost_'.$shipment->getId();
    }

    private function find(int $id): InPostShipment
    {
        return $this->repository->find($id) ?? throw new NotFoundHttpException();
    }

    private function assertCsrf(Request $request, InPostShipment $shipment): void
    {
        $token = new CsrfToken(self::csrfTokenId($shipment), (string) $request->request->get('_csrf_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new AccessDeniedHttpException('Nieprawidłowy token CSRF.');
        }
    }

    /** Operator podaje wymiary w centymetrach i wagę w kilogramach; ShipX chce milimetrów. */
    private function parcelFrom(Request $request, InPostShipment $shipment): Parcel
    {
        if (Service::requiresPoint($shipment->getService()) && '' !== (string) $request->request->get('template')) {
            return Parcel::template((string) $request->request->get('template'));
        }

        $cm = static fn (string $key): int => (int) round(((float) str_replace(',', '.', (string) $request->request->get($key))) * 10);

        return Parcel::dimensions($cm('length'), $cm('width'), $cm('height'), (float) str_replace(',', '.', (string) $request->request->get('weight')));
    }

    /** @return int|null kwota w groszach; puste pole = według polityki z konfiguracji */
    private function insuranceFrom(Request $request): ?int
    {
        $raw = trim((string) $request->request->get('insurance', ''));
        if ('' === $raw) {
            return null;
        }

        return max(0, (int) round(((float) str_replace([' ', ','], ['', '.'], $raw)) * 100));
    }

    private function flash(Request $request, string $type, string $message): void
    {
        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $message);
        }
    }

    private function backToOrder(InPostShipment $shipment): RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->generate('sylius_admin_order_show', [
            'id' => $shipment->getShipment()->getOrder()?->getId(),
        ]));
    }
}
