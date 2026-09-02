<?php

namespace Omnipay\Chapa\Message;

use Omnipay\Common\Message\ResponseInterface;

/**
 * Verify a Chapa transaction.
 *
 * The redirect back from the hosted checkout carries no trustworthy outcome,
 * so the payment status must be read from this server-side call.
 *
 * @link https://developer.chapa.co/docs/verify-payments/
 */
class CompletePurchaseRequest extends AbstractRequest
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
        return '/transaction/verify/' . rawurlencode((string) $this->getTransactionId());
    }

    protected function createResponse(array $data): ResponseInterface
    {
        return $this->response = new CompletePurchaseResponse($this, $data);
    }
}
