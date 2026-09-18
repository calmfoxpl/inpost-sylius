<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Validator;

use Calmfox\InPostBundle\Repository\InPostShipmentRepository;
use Calmfox\InPostBundle\Shipping\MethodMap;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class PointSelectedValidator extends ConstraintValidator
{
    public function __construct(
        private readonly MethodMap $methodMap,
        private readonly InPostShipmentRepository $repository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PointSelected) {
            throw new UnexpectedTypeException($constraint, PointSelected::class);
        }
        if (!$value instanceof OrderInterface) {
            return;
        }

        foreach ($value->getShipments() as $shipment) {
            if (!$this->methodMap->requiresPoint($shipment->getMethod())) {
                continue;
            }

            $point = $this->repository->findOneByShipment($shipment)?->getTargetPoint();
            if (null === $point || '' === $point) {
                $this->context->buildViolation($constraint->message)->addViolation();

                return;
            }
        }
    }
}
