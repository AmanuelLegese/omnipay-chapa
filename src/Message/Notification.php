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

        $decoded = json_decode($body, true, 512, JSON_BIGINT_AS_STRING);
        $this->data = is_array($decoded) ? $decoded : [];
    }

    public function getData(): array
    {
        return $this->data;
    }

    /**
     * The event name, e.g. `charge.success`, `charge.refunded`,
     * `payout.success`.
     */
    public function getEvent(): ?string
    {
        return $this->value('event');
    }

    /**
     * A customer payment event. Chapa delivers these and payout events to the
     * same URL with the same signature, so check this before treating a
     * completed event as a paid order.
     */
    public function isCharge(): bool
    {
        $event = $this->getEvent();

        if ($event !== null) {
            return str_starts_with($event, 'charge.');
        }

        return !$this->isPayout() && $this->value('tx_ref') !== null;
    }

    /**
     * A payout (transfer) event.
     */
    public function isPayout(): bool
    {
        $event = $this->getEvent();

        if ($event !== null) {
            return str_starts_with($event, 'payout.');
        }

        return strcasecmp((string) $this->value('type'), 'Payout') === 0;
    }

    /**
     * The merchant's own identifier: the tx_ref for a charge, or the reference
     * the merchant gave a payout.
     *
     * Kept separate from getTransactionReference() on purpose. A payout's
     * `reference` is the merchant's own payout id; reading it as a payment
     * reference is how a completed payout gets mistaken for a paid order.
     */
    public function getTransactionId(): ?string
    {
        return $this->isPayout() ? $this->value('reference') : $this->value('tx_ref');
    }

    /**
     * Chapa's identifier: `reference` for a charge (what refund() needs), or
     * `chapa_reference` for a payout.
     */
    public function getTransactionReference(): ?string
    {
        return $this->isPayout() ? $this->value('chapa_reference') : $this->value('reference');
    }

    /**
     * Omnipay's three-state status. NotificationInterface has no room for
     * refunds, so `refunded` and `reversed` report as failed here; use
     * isRefunded() / isReversed() or getStatus() to tell them apart.
     *
     * This describes whichever kind of event it is. A payout.success is also
     * completed - which is exactly why isCharge() must be checked first.
     *
     * @return NotificationInterface::STATUS_*
     */
    public function getTransactionStatus(): string
    {
        return match ($this->getStatus()) {
            'success' => self::STATUS_COMPLETED,
            'pending' => self::STATUS_PENDING,
            // Anything unrecognised is treated as failed rather than assumed
            // benign, so a new Chapa status never reads as a completed payment.
            default => self::STATUS_FAILED,
        };
    }

    /**
     * Chapa's raw status: success, pending, refunded, reversed, or the single
     * combined string `failed/cancelled`.
     */
    public function getStatus(): ?string
    {
        return $this->value('status');
    }

    /**
     * A charge that was refunded. Reported as STATUS_FAILED by
     * getTransactionStatus(), but the money was taken first - do not read it as
     * a payment that never happened.
     */
    public function isRefunded(): bool
    {
        return $this->getStatus() === 'refunded';
    }

    public function isReversed(): bool
    {
        return $this->getStatus() === 'reversed';
    }

    /**
     * How the customer paid. Webhooks call this `payment_method`, while the
     * verify endpoint calls the same thing `method`.
     */
    public function getPaymentMethod(): ?string
    {
        return $this->value('payment_method') ?? $this->value('method');
    }

    public function getAmount(): ?string
    {
        return $this->value('amount');
    }

    public function getCurrency(): ?string
    {
        return $this->value('currency');
    }

    /**
     * Chapa's explanation on a failed charge, e.g. a USSD prompt that timed out.
     */
    public function getFailureReason(): ?string
    {
        return $this->value('failure_reason');
    }

    /**
     * The failure reason when there is one, otherwise the event name.
     */
    public function getMessage(): ?string
    {
        return $this->getFailureReason() ?? $this->getEvent() ?? $this->value('message');
    }

    private function value(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return is_scalar($value) && $value !== '' ? (string) $value : null;
    }
}
