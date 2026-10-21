<?php

namespace Omnipay\Chapa\Message;

/**
 * The current state of a refund.
 */
class FetchRefundResponse extends AbstractResponse
{
    /**
     * The money has gone back to the customer.
     */
    public function isSuccessful(): bool
    {
        return $this->getRefundStatus() === 'refunded';
    }

    public function isPending(): bool
    {
        return in_array($this->getRefundStatus(), ['initiated', 'processing'], true);
    }

    /**
     * The refund could not be completed.
     */
    public function isReversed(): bool
    {
        return $this->getRefundStatus() === 'reversed';
    }

    /**
     * One of initiated, processing, refunded or reversed.
     */
    public function getRefundStatus(): ?string
    {
        return $this->getPaymentValue('status');
    }

    public function getRefundId(): ?string
    {
        return $this->getPaymentValue('ref_id');
    }

    public function getTransactionReference(): ?string
    {
        return $this->getRefundId();
    }

    /**
     * Chapa's reference for the payment that was refunded.
     */
    public function getPaymentReference(): ?string
    {
        return $this->getPaymentValue('payment_reference');
    }

    public function getMerchantReference(): ?string
    {
        return $this->getPaymentValue('merchant_reference');
    }

    public function getAmount(): ?string
    {
        return $this->getPaymentValue('amount');
    }

    public function getCurrency(): ?string
    {
        return $this->getPaymentValue('currency');
    }
}
