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
    /**
     * Test mode answers every transfer verify with `data: [null]` - a list, not
     * an object - even for a transfer created a moment earlier. Whether live
     * mode wraps the record in a list too could not be confirmed from test
     * mode, so accept both: unwrap a list to its first object.
     */
    protected function getPaymentData(): array
    {
        $data = $this->data['data'] ?? null;

        if (is_array($data) && array_is_list($data)) {
            $data = $data[0] ?? null;
        }

        return is_array($data) ? $data : [];
    }

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
