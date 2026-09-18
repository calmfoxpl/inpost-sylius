<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Shipping;

/** Dane jednego konta ShipX razem z informacją, skąd pochodzą — panel pokazuje źródło, nigdy token. */
final class Credentials
{
    public const SOURCE_PANEL = 'panel';
    public const SOURCE_CONFIG = 'config';
    public const SOURCE_NONE = 'none';

    public function __construct(
        public readonly string $token,
        public readonly string $organizationId,
        public readonly string $tokenSource,
        public readonly string $organizationSource,
    ) {
    }

    public function isComplete(): bool
    {
        return '' !== $this->token && '' !== $this->organizationId;
    }
}
