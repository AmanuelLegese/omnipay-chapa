<?php

namespace Omnipay\Chapa\Message;

/**
 * Response to verifying a payout.
 *
 * Chapa does not publish the full shape of the transfer `data` object, so the
 * accessors below cover the fields observed in their examples. Use getData()
 * for anything else rather than assuming a field is absent.
 */
class FetchTransferResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return $this->getTransferStatus() === 'success';
    }

    /**
     * Chapa is still moving the money. Not a failure - retry later.
     */
    public function isPending(): bool
    {
        return $this->getTransferStatus() === 'pending';
    }

    public function getTransferStatus(): ?string
    {
        return $this->getPaymentValue('status');
    }

    public function getTransactionReference(): ?string
    {
        return $this->getPaymentValue('reference') ?? $this->getPaymentValue('chapa_reference');
    }

    public function getAmount(): ?string
    {
        return $this->getPaymentValue('amount');
    }

    public function getCharge(): ?string
    {
        return $this->getPaymentValue('charge');
    }

    public function getCurrency(): ?string
    {
        return $this->getPaymentValue('currency');
    }

    public function getAccountNumber(): ?string
    {
        return $this->getPaymentValue('account_number');
    }

    public function getBankCode(): ?string
    {
        return $this->getPaymentValue('bank_code');
    }
}
