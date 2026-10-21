<?php

namespace Omnipay\Chapa\Message;

use Omnipay\Common\Exception\InvalidRequestException;
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
    /**
     * Longest reference Chapa accepts on a transfer, confirmed against the API.
     */
    public const MAX_REFERENCE_LENGTH = 36;

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

    public function getTestStatus(): ?string
    {
        return $this->getParameter('testStatus');
    }

    /**
     * Force the outcome of a test-mode payout: success, failed or pending.
     *
     * Creation still reports "queued" whatever this is set to, and test mode
     * stubs out transfer verification, so the forced outcome only shows up in
     * the payout webhook. Refused outright with a live key.
     *
     * @return $this
     */
    public function setTestStatus(?string $value): self
    {
        return $this->setParameter('testStatus', $value);
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
            // Chapa rejects anything longer with "The reference field need to
            // be 36 characters or less". Catching it here means the payout is
            // never sent, rather than sent and bounced.
            if (strlen($reference) > self::MAX_REFERENCE_LENGTH) {
                throw new InvalidRequestException(sprintf(
                    'The transactionId must be %d characters or fewer for a transfer; got %d',
                    self::MAX_REFERENCE_LENGTH,
                    strlen($reference)
                ));
            }
            $data['reference'] = $reference;
        }

        if (($testStatus = $this->getTestStatus()) !== null && $testStatus !== '') {
            if (!$this->isTestKey()) {
                throw new InvalidRequestException('testStatus can only be used with a CHASECK_TEST- secret key');
            }
            $data['status'] = $testStatus;
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
