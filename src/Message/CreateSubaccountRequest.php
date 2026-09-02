<?php

namespace Omnipay\Chapa\Message;

use Omnipay\Common\Message\ResponseInterface;

/**
 * Create a subaccount to split payments with.
 *
 * The returned subaccount id goes in PurchaseRequest's `subaccounts`.
 *
 * ## The endpoint path is not certain
 *
 * Chapa gives three different answers. Their split-payment docs and the
 * chapa-nestjs URL enum both say `/subaccount`; their own chapa-laravel package
 * posts to `/sub-accounts`; the chapa-laravel README says
 * `GET https://api.chapa.dev/v1/transaction/sub-accounts`, which is wrong on
 * host, method and path. We default to `/subaccount` on the strength of two
 * sources against one, and setEndpointPath() exists so nobody has to fork the
 * package if the sandbox says otherwise.
 *
 * @link https://developer.chapa.co/integrations/split-payment
 */
class CreateSubaccountRequest extends AbstractRequest
{
    public const SPLIT_TYPE_PERCENTAGE = 'percentage';
    public const SPLIT_TYPE_FLAT = 'flat';

    public const DEFAULT_ENDPOINT_PATH = '/subaccount';

    public function getBusinessName(): ?string
    {
        return $this->getParameter('businessName');
    }

    /**
     * @return $this
     */
    public function setBusinessName(?string $value): self
    {
        return $this->setParameter('businessName', $value);
    }

    public function getAccountName(): ?string
    {
        return $this->getParameter('accountName');
    }

    /**
     * @return $this
     */
    public function setAccountName(?string $value): self
    {
        return $this->setParameter('accountName', $value);
    }

    public function getAccountNumber(): ?string
    {
        return $this->getParameter('accountNumber');
    }

    /**
     * @return $this
     */
    public function setAccountNumber(?string $value): self
    {
        return $this->setParameter('accountNumber', $value);
    }

    public function getBankCode(): ?string
    {
        return $this->getParameter('bankCode');
    }

    /**
     * @return $this
     */
    public function setBankCode(?string $value): self
    {
        return $this->setParameter('bankCode', $value);
    }

    /**
     * Either percentage or flat. See the SPLIT_TYPE_* constants.
     */
    public function getSplitType(): ?string
    {
        return $this->getParameter('splitType');
    }

    /**
     * @return $this
     */
    public function setSplitType(?string $value): self
    {
        return $this->setParameter('splitType', $value);
    }

    /**
     * A fraction when splitType is percentage (0.03 is 3%), or an amount in the
     * subaccount's currency when it is flat.
     */
    public function getSplitValue()
    {
        return $this->getParameter('splitValue');
    }

    /**
     * @return $this
     */
    public function setSplitValue($value): self
    {
        return $this->setParameter('splitValue', $value);
    }

    public function getEndpointPath(): string
    {
        return $this->getParameter('endpointPath') ?: self::DEFAULT_ENDPOINT_PATH;
    }

    /**
     * Override the path if Chapa turns out to expose this at /sub-accounts.
     *
     * @return $this
     */
    public function setEndpointPath(?string $value): self
    {
        return $this->setParameter('endpointPath', $value);
    }

    public function getData(): array
    {
        $this->validate(
            'secretKey',
            'businessName',
            'accountName',
            'accountNumber',
            'bankCode',
            'splitType',
            'splitValue'
        );

        return [
            'business_name' => $this->getBusinessName(),
            'account_name' => $this->getAccountName(),
            'bank_code' => $this->getBankCode(),
            'account_number' => $this->getAccountNumber(),
            'split_type' => $this->getSplitType(),
            'split_value' => $this->getSplitValue(),
        ];
    }

    protected function getEndpoint(): string
    {
        return $this->getEndpointPath();
    }

    protected function createResponse(array $data): ResponseInterface
    {
        return $this->response = new CreateSubaccountResponse($this, $data);
    }
}
