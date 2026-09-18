<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Checkout;

use Calmfox\InPostBundle\Api\PointsClient;
use Calmfox\InPostBundle\Api\PointsUnavailableException;
use Calmfox\InPostBundle\Core\PhoneNumber;
use Calmfox\InPostBundle\Entity\InPostShipment;
use Calmfox\InPostBundle\Repository\InPostShipmentRepository;
use Calmfox\InPostBundle\Shipping\MethodMap;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\CoreBundle\Form\Type\Checkout\ShipmentType;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Wybór paczkomatu jest polem formularza kroku dostawy, a nie osobnym żądaniem AJAX:
 * punkt zapisuje się razem z metodą dostawy, a jego brak jest zwykłym błędem formularza,
 * który Sylius pokaże przy metodzie. Nie potrzeba blokowania przycisku „Dalej" w JS
 * ani osobnego walidatora zamówienia „na wszelki wypadek".
 *
 * Wiersz z punktem zapisujemy przez persist() bez flush(): flush robi Sylius po udanym
 * zatwierdzeniu kroku, więc przy błędzie formularza nic nie trafia do bazy.
 */
final class ShipmentTypeExtension extends AbstractTypeExtension
{
    public const FIELD = 'inpostPoint';

    public function __construct(
        private readonly MethodMap $methodMap,
        private readonly InPostShipmentRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PointsClient $pointsClient,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [ShipmentType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ([] === $this->methodMap->pointMethodCodes()) {
            return;
        }

        $builder->add(self::FIELD, HiddenType::class, ['mapped' => false, 'required' => false]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event): void {
            $shipment = $event->getData();
            if ($shipment instanceof ShipmentInterface && $event->getForm()->has(self::FIELD)) {
                $event->getForm()->get(self::FIELD)->setData($this->repository->findOneByShipment($shipment)?->getTargetPoint());
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $shipment = $event->getData();
            if ($shipment instanceof ShipmentInterface) {
                $this->onSubmit($event, $shipment);
            }
        });
    }

    private function onSubmit(FormEvent $event, ShipmentInterface $shipment): void
    {
        $form = $event->getForm();
        $existing = $this->repository->findOneByShipment($shipment);
        $service = $this->methodMap->serviceFor($shipment->getMethod());

        if (null === $service) {
            if (null !== $existing && !$existing->isDispatched()) {
                $this->entityManager->remove($existing);
            }

            return;
        }

        $errorTarget = $form->has('method') ? $form->get('method') : $form;

        $order = $shipment->getOrder();
        $phone = $order instanceof OrderInterface ? $order->getShippingAddress()?->getPhoneNumber() : null;
        if (null === PhoneNumber::normalize($phone)) {
            $errorTarget->addError(new FormError($this->translator->trans('calmfox_inpost.checkout.phone_invalid', [], 'validators')));

            return;
        }

        $inPostShipment = $existing ?? new InPostShipment($shipment, $service);
        $inPostShipment->setService($service);

        if (!$this->methodMap->requiresPoint($shipment->getMethod())) {
            $inPostShipment->setTargetPoint(null);
            $this->entityManager->persist($inPostShipment);

            return;
        }

        $name = mb_strtoupper(trim((string) $form->get(self::FIELD)->getData()));
        if ('' === $name) {
            $errorTarget->addError(new FormError($this->translator->trans('calmfox_inpost.checkout.point_required', [], 'validators')));

            return;
        }

        if ($name !== $inPostShipment->getTargetPoint()) {
            try {
                $point = $this->pointsClient->find($name);
            } catch (PointsUnavailableException) {
                // Awaria API punktów nie może zatrzymać sprzedaży. Kod pochodzi z naszej listy,
                // a ShipX i tak sprawdzi go przy nadaniu — operator zobaczy wtedy czytelny błąd.
                $point = null;
                if (1 === preg_match('/^[A-Z0-9-]{4,32}$/', $name)) {
                    $inPostShipment->setTargetPoint($name);
                    $this->entityManager->persist($inPostShipment);

                    return;
                }
            }

            if (null === $point || !$point->operating) {
                $errorTarget->addError(new FormError($this->translator->trans('calmfox_inpost.checkout.point_unknown', ['%point%' => $name], 'validators')));

                return;
            }

            $inPostShipment->setTargetPoint($point->name, $point->address);
        }

        $this->entityManager->persist($inPostShipment);
    }
}
