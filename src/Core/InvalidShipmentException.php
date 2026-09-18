<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Core;

/** Danych zamówienia nie da się nadać; komunikat jest przeznaczony dla operatora panelu. */
final class InvalidShipmentException extends \DomainException
{
}
