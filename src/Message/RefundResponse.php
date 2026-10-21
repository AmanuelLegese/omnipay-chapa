<?php

namespace Omnipay\Chapa\Message;

/**
 * Response to initiating a refund.
 *
 * Success means Chapa accepted the refund, which starts life as `initiated`
 * and moves through `processing` to `refunded` or `reversed`. Track it with
 * fetchRefund() using getRefundId().
 */
class RefundResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return $this->getCode() === 'success';
    }

    public function isPending(): bool
    {
        return in_array($this->getRefundStatus(), ['initiated', 'processing'], true);
    }

    public function getRefundStatus(): ?string
    {
        return $this->getPaymentValue('status');
    }

    /**
     * Chapa's id for the refund, needed by fetchRefund().
     */
    public function getRefundId(): ?string
    {
        return $this->getPaymentValue('ref_id');
    }

    /**
     * In Omnipay terms the refund's own gateway reference, so the same value as
     * getRefundId().
     */
    public function getTransactionReference(): ?string
    {
        return $this->getRefundId();
    }

    /**
     * Chapa's reference for the payment that was refunded.
     */
    public function getPaymentReference(): ?string
    {
        return $this->getPaymentValue('chapa_reference');
    }

    /**
     * The refundReference that was sent, if any.
     */
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
