<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Core;

/**
 * Ile ubezpieczyć, gdy operator nie poda kwoty sam. Domyślnie wartość zamówienia, bo stała
 * kwota z konfiguracji jest zawsze błędna: za wysoka dla skarpet, za niska dla płaszcza.
 * Górny limit jest po to, żeby nie wysyłać do ShipX kwoty, której usługa nie przyjmie —
 * zamówienia droższe niż limit należy odciąć regułą metody dostawy („wartość zamówienia
 * mniejsza lub równa"), a nie nadawać niedoubezpieczone.
 */
final class InsurancePolicy
{
    public const MODE_ORDER_TOTAL = 'order_total';
    public const MODE_NONE = 'none';

    public function __construct(
        private readonly string $mode = self::MODE_ORDER_TOTAL,
        private readonly ?int $maxAmount = null,
    ) {
    }

    /** @param int $orderTotal w groszach @return int|null w groszach */
    public function amountFor(int $orderTotal): ?int
    {
        if (self::MODE_NONE === $this->mode || $orderTotal <= 0) {
            return null;
        }

        return null === $this->maxAmount ? $orderTotal : min($orderTotal, $this->maxAmount);
    }

    public function exceedsLimit(int $orderTotal): bool
    {
        return self::MODE_NONE !== $this->mode && null !== $this->maxAmount && $orderTotal > $this->maxAmount;
    }
}
