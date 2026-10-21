<?php

namespace Omnipay\Chapa\Message;

/**
 * Response to a transaction verify.
 *
 * Reports what Chapa says about the transaction. It deliberately does not
 * compare the returned amount or currency against what the merchant asked
 * for; callers that need that guarantee should check getAmount() and
 * getCurrency() themselves.
 *
 * ## Statuses observed against the real API
 *
 * - `pending`: not paid yet. Also what a *cancelled* checkout keeps reporting,
 *   because cancelling expires the link without changing this status.
 * - `success`: paid.
 * - `failed/cancelled`: one literal string, slash included. Chapa does not say
 *   which of the two happened.
 * - `refunded`: set on the whole transaction by *any* refund, including a
 *   partial one. A payment that was half refunded reads as refunded here.
 * - `reversed`: a refund that could not be completed.
 */
class FetchTransactionResponse extends AbstractResponse
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_PENDING = 'pending';
    public const STATUS_FAILED_OR_CANCELLED = 'failed/cancelled';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_REVERSED = 'reversed';

    /**
     * True only for a payment that is currently paid and not refunded.
     *
     * A partially refunded payment is *not* successful by this test, because
     * Chapa marks the whole transaction refunded. Check isRefunded() before
     * concluding a payment never happened.
     */
    public function isSuccessful(): bool
    {
        return $this->getPaymentStatus() === self::STATUS_SUCCESS;
    }

    /**
     * The customer has not finished paying yet. Chapa keeps returning this
     * until the transaction settles, so it is worth retrying rather than
     * treating as a failure.
     *
     * A checkout cancelled through void() also stays pending - cancelling
     * expires the link without updating this status - so the void() response
     * is the only record that a cancellation happened.
     */
    public function isPending(): bool
    {
        return $this->getPaymentStatus() === self::STATUS_PENDING;
    }

    /**
     * The payment did not go through. Covers Chapa's combined
     * `failed/cancelled` status as well as either word on its own.
     */
    public function isFailed(): bool
    {
        return in_array(
            $this->getPaymentStatus(),
            [self::STATUS_FAILED_OR_CANCELLED, 'failed', 'cancelled'],
            true
        );
    }

    /**
     * Only true for a bare `cancelled`. Chapa normally reports the combined
     * `failed/cancelled`, which cannot be told apart, so that answers false
     * here and true from isFailed().
     */
    public function isCancelled(): bool
    {
        return $this->getPaymentStatus() === 'cancelled';
    }

    /**
     * The payment was taken and then refunded, wholly or in part. The amount
     * refunded is not on this response; fetch the refund itself for that.
     */
    public function isRefunded(): bool
    {
        return $this->getPaymentStatus() === self::STATUS_REFUNDED;
    }

    /**
     * A refund was attempted and could not be completed.
     */
    public function isReversed(): bool
    {
        return $this->getPaymentStatus() === self::STATUS_REVERSED;
    }

    /**
     * Chapa's raw status string - see the class docblock for the observed
     * values - or null when Chapa returned no data object, which is what an
     * error response looks like.
     */
    public function getPaymentStatus(): ?string
    {
        return $this->getPaymentValue('status');
    }

    /**
     * Chapa's own reference for the transaction. This, not the merchant's
     * tx_ref, is what refund() needs. Null until the customer has attempted to
     * pay.
     */
    public function getTransactionReference(): ?string
    {
        return $this->getPaymentValue('reference');
    }

    /**
     * The tx_ref the merchant supplied at initialize time.
     */
    public function getTransactionId(): ?string
    {
        return $this->getPaymentValue('tx_ref');
    }

    /**
     * Chapa sends amounts as JSON numbers. They are returned verbatim as
     * strings rather than reformatted, so no precision is invented.
     */
    public function getAmount(): ?string
    {
        return $this->getPaymentValue('amount');
    }

    /**
     * Chapa's fee for this transaction. Null until the customer has attempted
     * to pay.
     */
    public function getCharge(): ?string
    {
        return $this->getPaymentValue('charge');
    }

    public function getCurrency(): ?string
    {
        return $this->getPaymentValue('currency');
    }

    /**
     * How the customer paid, e.g. telebirr. Always the literal `test` in test
     * mode, whatever method was actually used.
     */
    public function getPaymentMethod(): ?string
    {
        return $this->getPaymentValue('method');
    }

    /**
     * Either test or live, reflecting the secret key the request used.
     */
    public function getMode(): ?string
    {
        return $this->getPaymentValue('mode');
    }

    /**
     * The customer's phone as Chapa stored it. Chapa rewrites local numbers to
     * international form, so `0911223344` sent at initialize comes back as
     * `251911223344`.
     */
    public function getPhoneNumber(): ?string
    {
        return $this->getPaymentValue('phone_number');
    }

    /**
     * The title, description and logo the checkout page was rendered with.
     *
     * @return array<string, mixed>
     */
    public function getCustomization(): array
    {
        $customization = $this->getPaymentData()['customization'] ?? null;

        return is_array($customization) ? $customization : [];
    }

    /**
     * Whatever was passed as `meta` at initialize time, echoed back untouched.
     *
     * @return array<string, mixed>|null
     */
    public function getMeta(): ?array
    {
        $meta = $this->getPaymentData()['meta'] ?? null;

        return is_array($meta) ? $meta : null;
    }
}
