<?php

namespace Omnipay\Chapa\Message;

use Omnipay\Common\Message\ResponseInterface;

/**
 * Cancel an unpaid checkout, expiring its link immediately.
 *
 * Only active transactions can be cancelled, and it cannot be undone.
 *
 * Verify does not reflect the cancellation: a cancelled transaction keeps
 * reporting `pending` (confirmed against the API), so record the outcome of
 * this call rather than expecting fetchTransaction() to show it later.
 *
 * @link https://developer.chapa.co/integrations/transaction-cancel
 */
class VoidRequest extends AbstractRequest
{
    public function getData(): array
    {
        $this->validate('secretKey', 'transactionId');

        return [];
    }

    protected function getHttpMethod(): string
    {
        return 'PUT';
    }

    protected function getEndpoint(): string
    {
        return '/transaction/cancel/' . rawurlencode((string) $this->getTransactionId());
    }

    protected function createResponse(array $data): ResponseInterface
    {
        return $this->response = new VoidResponse($this, $data);
    }
}
