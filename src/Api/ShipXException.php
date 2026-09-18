<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Api;

/**
 * Błąd ShipX w postaci, którą da się pokazać operatorowi. ShipX odpowiada
 * `{"error": "validation_failed", "message": "...", "details": {"receiver": [{"phone": ["invalid"]}]}}`
 * — spłaszczamy `details` do ścieżek („receiver.phone: invalid"), bo sam `message`
 * mówi zwykle tylko „sprawdź szczegóły".
 */
final class ShipXException extends \RuntimeException
{
    /** @param list<string> $details */
    public function __construct(string $message, private readonly int $statusCode = 0, private readonly array $details = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function fromResponse(int $statusCode, string $body): self
    {
        $data = json_decode($body, true);
        if (!\is_array($data)) {
            return new self(sprintf('InPost odpowiedział kodem %d.', $statusCode), $statusCode);
        }

        $error = \is_string($data['error'] ?? null) ? $data['error'] : null;
        $message = \is_string($data['message'] ?? null) ? $data['message'] : null;

        $summary = match (true) {
            401 === $statusCode => 'InPost odrzucił token API (401). Sprawdź, czy token jest kompletny i należy do tej organizacji.',
            403 === $statusCode => 'Token API nie ma uprawnień do tej operacji (403).',
            null !== $message => sprintf('InPost: %s', $message),
            default => sprintf('InPost odpowiedział kodem %d%s.', $statusCode, null !== $error ? ' ('.$error.')' : ''),
        };

        $details = [];
        if (\is_array($data['details'] ?? null)) {
            self::flatten($data['details'], '', $details);
        }

        return new self($summary, $statusCode, $details);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** @return list<string> */
    public function getDetails(): array
    {
        return $this->details;
    }

    public function getOperatorMessage(): string
    {
        return [] === $this->details ? $this->getMessage() : $this->getMessage().' '.implode('; ', $this->details).'.';
    }

    /**
     * @param array<array-key, mixed> $node
     * @param list<string>            $out
     */
    private static function flatten(array $node, string $path, array &$out): void
    {
        foreach ($node as $key => $value) {
            $here = \is_int($key) ? $path : ('' === $path ? (string) $key : $path.'.'.$key);
            if (\is_array($value)) {
                self::flatten($value, $here, $out);
            } elseif (\is_scalar($value)) {
                $out[] = '' === $here ? (string) $value : $here.': '.$value;
            }
        }
    }
}
