<?php

namespace Omnipay\Chapa\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\NotificationInterface;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

/**
 * An incoming Chapa webhook, verified on construction.
 *
 * ## Why only x-chapa-signature is trusted
 *
 * Chapa sends two signature headers and they are not equivalent:
 *
 * - `x-chapa-signature` is an HMAC-SHA256 of the event payload.
 * - `Chapa-Signature` is an HMAC-SHA256 of the secret key keyed by the secret
 *   key itself, which makes it a constant. It is identical on every webhook
 *   you will ever receive, so it proves the sender knew the secret at some
 *   point but says nothing about the body. Anyone who observes it once can
 *   replay it against a forged payload.
 *
 * Chapa's documentation says either header is sufficient. Accepting the
 * constant would mean a forged "payment succeeded" event validates, so this
 * class deliberately ignores `Chapa-Signature` and requires the payload HMAC.
 * There is no case where the constant adds assurance once the payload HMAC has
 * already verified, which is why it is not offered as a secondary check.
 *
 * @link https://developer.chapa.co/chapa-merchant-handbook
 */
class Notification implements NotificationInterface
{
    public const SIGNATURE_HEADER = 'x-chapa-signature';

    private array $data;

    /**
     * @throws InvalidRequestException when the payload signature is absent or
     *                                 does not verify. Failing closed means a
     *                                 caller cannot accidentally act on an
     *                                 unauthenticated event.
     */
    public function __construct(HttpRequest $httpRequest, ?string $secret)
    {
        if ($secret === null || $secret === '') {
            throw new InvalidRequestException('The webhookSecret parameter is required');
        }

        $signature = $httpRequest->headers->get(self::SIGNATURE_HEADER);

        if ($signature === null || $signature === '') {
            throw new InvalidRequestException(
                'Missing ' . self::SIGNATURE_HEADER . ' header on Chapa webhook'
            );
        }

        // Hash the body exactly as it arrived. Decoding and re-encoding does
        // not round-trip byte for byte (key order, unicode escaping, float
        // formatting), which would break otherwise valid signatures.
        $body = $httpRequest->getContent();

        if (!hash_equals(hash_hmac('sha256', $body, $secret), $signature)) {
            throw new InvalidRequestException('Invalid Chapa webhook signature');
        }

        $decoded = json_decode($body, true);
        $this->data = is_array($decoded) ? $decoded : [];
    }

    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Chapa's own reference, falling back to the merchant's tx_ref when the
     * event carries no reference of its own.
     */
    public function getTransactionReference(): ?string
    {
        foreach (['reference', 'tx_ref'] as $key) {
            $value = $this->data[$key] ?? null;
            if (is_scalar($value) && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @return NotificationInterface::STATUS_*
     */
    public function getTransactionStatus(): string
    {
        $status = $this->data['status'] ?? null;

        return match ($status) {
            'success' => self::STATUS_COMPLETED,
            'pending' => self::STATUS_PENDING,
            // Anything unrecognised is treated as failed rather than assumed
            // benign, so a new Chapa status never reads as a completed payment.
            default => self::STATUS_FAILED,
        };
    }

    public function getMessage(): ?string
    {
        foreach (['event', 'message'] as $key) {
            $value = $this->data[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
