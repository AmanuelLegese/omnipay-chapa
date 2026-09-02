<?php

namespace Omnipay\Chapa\Message;

/**
 * Response to a transaction verify.
 *
 * Reports what Chapa says about the transaction. It deliberately does not
 * compare the returned amount or currency against what the merchant asked
 * for; callers that need that guarantee should check getAmount() and
 * getCurrency() themselves.
 */
class CompletePurchaseResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return $this->getPaymentStatus() === 'success';
    }

    /**
     * The customer has not finished paying yet. Chapa keeps returning this
     * until the transaction settles, so it is worth retrying rather than
     * treating as a failure.
     */
    public function isPending(): bool
    {
        return $this->getPaymentStatus() === 'pending';
    }

    /**
     * One of success, pending or failed - or null when Chapa returned no data
     * object at all, which is what an error response looks like.
     */
    public function getPaymentStatus(): ?string
    {
        return $this->getPaymentValue('status');
    }

    /**
     * Chapa's own reference for the transaction.
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
     * Chapa's fee for this transaction.
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
     * How the customer paid, e.g. telebirr or a card scheme.
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
}
