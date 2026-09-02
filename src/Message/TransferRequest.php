<?php

namespace Omnipay\Chapa\Message;

use Omnipay\Common\Message\ResponseInterface;

/**
 * Initiate a payout to a bank account.
 *
 * Chapa validates the destination account itself, but a wrong bank_code sends
 * money to a valid account at the wrong bank, so resolve codes with
 * fetchBanks() rather than hard-coding them.
 *
 * @link https://developer.chapa.co/transfer/transfers
 */
class TransferRequest extends AbstractRequest
{
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

    public function getData(): array
    {
        $this->validate('secretKey', 'amount', 'accountNumber', 'bankCode');

        $data = [
            'account_number' => $this->getAccountNumber(),
            'amount' => $this->getAmount(),
            'bank_code' => $this->getBankCode(),
            'currency' => $this->getCurrency() ?: PurchaseRequest::DEFAULT_CURRENCY,
        ];

        if ($accountName = $this->getAccountName()) {
            $data['account_name'] = $accountName;
        }

        // Chapa generates a reference when none is given, but then the only
        // way to find the payout again is to read it back off the response.
        if ($reference = $this->getTransactionId()) {
            $data['reference'] = $reference;
        }

        return $data;
    }

    protected function getEndpoint(): string
    {
        return '/transfers';
    }

    protected function createResponse(array $data): ResponseInterface
    {
        return $this->response = new TransferResponse($this, $data);
    }
}
