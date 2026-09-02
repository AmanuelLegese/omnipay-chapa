<?php

namespace Omnipay\Chapa\Message;

use Omnipay\Common\Message\ResponseInterface;

/**
 * Verify a payout.
 *
 * @link https://developer.chapa.co/transfer/verify-transfers
 */
class FetchTransferRequest extends AbstractRequest
{
    public function getData(): array
    {
        $this->validate('secretKey', 'transactionId');

        return [];
    }

    protected function getHttpMethod(): string
    {
        return 'GET';
    }

    protected function getEndpoint(): string
    {
        return '/transfers/verify/' . rawurlencode((string) $this->getTransactionId());
    }

    protected function createResponse(array $data): ResponseInterface
    {
        return $this->response = new FetchTransferResponse($this, $data);
    }
}
