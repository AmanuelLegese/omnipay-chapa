<?php

namespace Omnipay\Chapa\Message;

/**
 * Response to a bulk payout.
 *
 * Success means Chapa queued the batch, not that every row settled. Each row
 * has to be confirmed individually with fetchTransfer() on its reference.
 */
class BulkTransferResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return $this->getCode() === 'success';
    }

    /**
     * Chapa's identifier for the queued batch, when it sends one.
     */
    public function getBulkId(): ?string
    {
        $data = $this->data['data'] ?? null;

        if (is_scalar($data) && $data !== '') {
            return (string) $data;
        }

        return $this->getPaymentValue('id') ?? $this->getPaymentValue('bulk_id');
    }
}
