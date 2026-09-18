<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Tests\Validator;

use Calmfox\InPostBundle\Core\Service;
use Calmfox\InPostBundle\Entity\InPostShipment;
use Calmfox\InPostBundle\Repository\InPostShipmentRepository;
use Calmfox\InPostBundle\Shipping\MethodMap;
use Calmfox\InPostBundle\Validator\PointSelected;
use Calmfox\InPostBundle\Validator\PointSelectedValidator;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\Shipment;
use Sylius\Component\Core\Model\ShippingMethod;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/** @extends ConstraintValidatorTestCase<PointSelectedValidator> */
final class PointSelectedValidatorTest extends ConstraintValidatorTestCase
{
    private ?InPostShipment $stored = null;

    protected function createValidator(): PointSelectedValidator
    {
        $repository = $this->createStub(InPostShipmentRepository::class);
        $repository->method('findOneByShipment')->willReturnCallback(fn (): ?InPostShipment => $this->stored);

        return new PointSelectedValidator(
            new MethodMap(['inpost_point' => Service::LOCKER_STANDARD, 'inpost' => Service::COURIER_STANDARD]),
            $repository,
        );
    }

    public function testLockerMethodWithoutPointIsAViolation(): void
    {
        $this->validator->validate($this->order('inpost_point'), new PointSelected());

        $this->buildViolation('calmfox_inpost.checkout.point_required')->assertRaised();
    }

    public function testLockerMethodWithPointPasses(): void
    {
        $order = $this->order('inpost_point');
        $this->stored = new InPostShipment($order->getShipments()->first(), Service::LOCKER_STANDARD);
        $this->stored->setTargetPoint('WAW23N', 'Zwoleńska 59');

        $this->validator->validate($order, new PointSelected());

        $this->assertNoViolation();
    }

    public function testCourierAndForeignMethodsNeedNoPoint(): void
    {
        $this->validator->validate($this->order('inpost'), new PointSelected());
        $this->validator->validate($this->order('odbior_salon'), new PointSelected());

        $this->assertNoViolation();
    }

    private function order(string $methodCode): Order
    {
        $method = new ShippingMethod();
        $method->setCode($methodCode);
        $shipment = new Shipment();
        $shipment->setMethod($method);
        $order = new Order();
        $order->addShipment($shipment);

        return $order;
    }
}
