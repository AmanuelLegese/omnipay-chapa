<?php

namespace Omnipay\Chapa\Message;

/**
 * Response to cancelling a checkout.
 *
 * Cancelling one that is already expired fails with "Payment link already
 * expired"; an unknown tx_ref fails with "Payment link not found".
 */
class VoidResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return $this->getCode() === 'success';
    }

    public function getTransactionId(): ?string
    {
        return $this->getPaymentValue('tx_ref');
    }
}
