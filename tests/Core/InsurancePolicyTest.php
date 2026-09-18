<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Tests\Core;

use Calmfox\InPostBundle\Core\InsurancePolicy;
use PHPUnit\Framework\TestCase;

final class InsurancePolicyTest extends TestCase
{
    public function testOrderTotalByDefault(): void
    {
        self::assertSame(419900, (new InsurancePolicy())->amountFor(419900));
    }

    public function testLimitCapsAmountAndIsReported(): void
    {
        $policy = new InsurancePolicy(InsurancePolicy::MODE_ORDER_TOTAL, 2000000);

        self::assertSame(2000000, $policy->amountFor(3369900));
        self::assertTrue($policy->exceedsLimit(3369900));
        self::assertFalse($policy->exceedsLimit(419900));
    }

    public function testNone(): void
    {
        $policy = new InsurancePolicy(InsurancePolicy::MODE_NONE, 100);

        self::assertNull($policy->amountFor(419900));
        self::assertFalse($policy->exceedsLimit(419900));
    }
}
