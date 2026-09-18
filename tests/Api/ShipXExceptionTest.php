<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Tests\Api;

use Calmfox\InPostBundle\Api\ShipXException;
use PHPUnit\Framework\TestCase;

final class ShipXExceptionTest extends TestCase
{
    public function testValidationDetailsAreFlattenedForTheOperator(): void
    {
        $e = ShipXException::fromResponse(400, (string) json_encode([
            'status' => 400,
            'error' => 'validation_failed',
            'message' => 'Check details object for more info.',
            'details' => ['receiver' => [['phone' => ['invalid']]], 'custom_attributes' => [['target_point' => ['does_not_exist']]]],
        ]));

        self::assertSame(400, $e->getStatusCode());
        self::assertSame(['receiver.phone: invalid', 'custom_attributes.target_point: does_not_exist'], $e->getDetails());
        self::assertStringContainsString('receiver.phone: invalid; custom_attributes.target_point: does_not_exist.', $e->getOperatorMessage());
    }

    public function testInvalidTokenGetsAHumanExplanation(): void
    {
        $e = ShipXException::fromResponse(401, '{"status":401,"error":"token_invalid","message":"Token is missing or invalid."}');

        self::assertStringContainsString('odrzucił token', $e->getMessage());
    }

    public function testNonJsonBody(): void
    {
        self::assertSame('InPost odpowiedział kodem 502.', ShipXException::fromResponse(502, '<html>Bad gateway</html>')->getOperatorMessage());
    }
}
