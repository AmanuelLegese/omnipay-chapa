<?php

namespace Omnipay\Chapa\Message;

/**
 * Response to initiating a payout.
 *
 * A successful response means Chapa accepted the payout for processing, not
 * that the money has landed. Confirm settlement with fetchTransfer().
 */
class TransferResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return $this->getCode() === 'success';
    }

    /**
     * Chapa returns the payout reference as a bare string in `data` rather than
     * as an object, so this reads the envelope directly.
     */
    public function getTransactionReference(): ?string
    {
        $data = $this->data['data'] ?? null;

        if (is_scalar($data) && $data !== '') {
            return (string) $data;
        }

        return $this->getPaymentValue('reference');
    }
}
