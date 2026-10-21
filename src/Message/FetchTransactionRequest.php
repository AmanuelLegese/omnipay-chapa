<?php

namespace Omnipay\Chapa\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Verify a Chapa transaction by the merchant's own tx_ref.
 *
 * This is Omnipay's standard fetchTransaction(). Use it anywhere - a cron job,
 * an admin screen - where the reference is known. On the customer's return
 * from checkout, completePurchase() does the same call but can also read the
 * reference off the incoming request.
 *
 * @link https://developer.chapa.co/integrations/verify-payments
 */
class FetchTransactionRequest extends AbstractRequest
{
    public function getData(): array
    {
        $this->validate('secretKey');

        // Deliberately not $this->validate('transactionId'): that reads the
        // parameter bag directly, which would bypass a subclass that resolves
        // the reference from somewhere else.
        if (($this->getTransactionId() ?? '') === '') {
            throw new InvalidRequestException('The transactionId parameter is required');
        }

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
        return $this->response = new FetchTransactionResponse($this, $data);
    }
}
