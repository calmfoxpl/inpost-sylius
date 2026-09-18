<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Tests\Core;

use Calmfox\InPostBundle\Core\SecretBox;
use PHPUnit\Framework\TestCase;

final class SecretBoxTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $box = new SecretBox('app-secret');
        $stored = $box->encrypt('eyJhbGciOi.payload.signature');

        self::assertStringStartsWith('v1:', $stored);
        self::assertStringNotContainsString('payload', $stored);
        self::assertSame('eyJhbGciOi.payload.signature', $box->decrypt($stored));
    }

    public function testSameTokenEncryptsDifferentlyEachTime(): void
    {
        $box = new SecretBox('app-secret');

        self::assertNotSame($box->encrypt('token'), $box->encrypt('token'));
    }

    public function testChangedApplicationSecretMeansNoToken(): void
    {
        $stored = (new SecretBox('old-secret'))->encrypt('token');

        self::assertNull((new SecretBox('new-secret'))->decrypt($stored));
    }

    public function testGarbageAndTamperingAreRejected(): void
    {
        $box = new SecretBox('app-secret');
        $stored = $box->encrypt('token');

        self::assertNull($box->decrypt(null));
        self::assertNull($box->decrypt('plain-token-someone-put-in-the-column'));
        self::assertNull($box->decrypt('v1:'.base64_encode('short')));
        self::assertNull($box->decrypt(substr($stored, 0, -4).'AAAA'));
    }

    public function testEmptyApplicationSecretIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SecretBox('');
    }
}
