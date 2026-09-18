<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Ostatnia zapora przed złożeniem zamówienia. Normalnie punkt wymusza formularz kroku dostawy;
 * ta reguła łapie przypadek, w którym Sylius sam przestawił metodę na paczkomat (poprzednia
 * przestała być dostępna po zmianie koszyka), a klient nie przeszedł kroku dostawy ponownie.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class PointSelected extends Constraint
{
    public string $message = 'calmfox_inpost.checkout.point_required';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
