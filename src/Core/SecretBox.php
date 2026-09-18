<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Core;

/**
 * Szyfrowanie tokenów zapisywanych z panelu. Token w bazie jawnym tekstem wycieka z każdym
 * zrzutem i każdą kopią zapasową; zaszyfrowany kluczem wyprowadzonym z sekretu aplikacji jest
 * bezużyteczny bez dostępu do jej środowiska. XSalsa20-Poly1305 (libsodium), nonce na wiadomość.
 *
 * Zmiana sekretu aplikacji unieważnia zapisane tokeny — odczyt zwraca wtedy null i panel
 * pokazuje „brak danych konta", zamiast wysyłać do InPostu śmieci.
 */
final class SecretBox
{
    private const PREFIX = 'v1:';

    private readonly string $key;

    public function __construct(string $applicationSecret)
    {
        if ('' === $applicationSecret) {
            throw new \InvalidArgumentException('Sekret aplikacji jest pusty — nie da się bezpiecznie zapisać tokenu.');
        }

        $this->key = sodium_crypto_generichash('calmfox/inpost-sylius:'.$applicationSecret, '', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::PREFIX.base64_encode($nonce.sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    public function decrypt(?string $stored): ?string
    {
        if (null === $stored || !str_starts_with($stored, self::PREFIX)) {
            return null;
        }

        $raw = base64_decode(substr($stored, \strlen(self::PREFIX)), true);
        if (false === $raw || \strlen($raw) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $plain = sodium_crypto_secretbox_open(
            substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key,
        );

        return false === $plain ? null : $plain;
    }
}
