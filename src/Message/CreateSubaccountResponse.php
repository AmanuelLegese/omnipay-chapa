<?php

namespace Omnipay\Chapa\Message;

/**
 * Response to creating a subaccount.
 *
 * Like TransferResponse, Chapa returns the new id as a bare string in `data`
 * rather than as an object.
 */
class CreateSubaccountResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return $this->getCode() === 'success';
    }

    /**
     * The subaccount id to pass to PurchaseRequest::setSubaccounts().
     */
    public function getSubaccountId(): ?string
    {
        $data = $this->data['data'] ?? null;

        if (is_scalar($data) && $data !== '') {
            return (string) $data;
        }

        return $this->getPaymentValue('subaccount_id') ?? $this->getPaymentValue('id');
    }
}
