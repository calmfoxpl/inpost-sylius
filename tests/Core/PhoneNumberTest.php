<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Tests\Core;

use Calmfox\InPostBundle\Core\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    /** @return iterable<string, array{?string, ?string}> */
    public static function numbers(): iterable
    {
        yield 'z prefiksem i spacjami' => ['+48 605 203 478', '605203478'];
        yield 'z myślnikami' => ['605-203-478', '605203478'];
        yield 'prefiks 0048' => ['0048605203478', '605203478'];
        yield 'prefiks 48 bez plusa' => ['48605203478', '605203478'];
        yield 'dziewięć cyfr zaczynających się od 48' => ['485203478', '485203478'];
        yield 'stacjonarny warszawski' => ['22 123 45 67', null];
        yield 'za krótki' => ['60520347', null];
        yield 'zagraniczny' => ['+49 151 23456789', null];
        yield 'pusty' => ['', null];
        yield 'brak' => [null, null];
    }

    #[DataProvider('numbers')]
    public function testNormalize(?string $raw, ?string $expected): void
    {
        self::assertSame($expected, PhoneNumber::normalize($raw));
    }
}
