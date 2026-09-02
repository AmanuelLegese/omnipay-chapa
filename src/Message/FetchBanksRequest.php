<?php

namespace Omnipay\Chapa\Message;

use Omnipay\Common\Message\ResponseInterface;

/**
 * List the banks Chapa can pay out to, with the codes TransferRequest needs.
 *
 * @link https://developer.chapa.co/transfer/list-banks
 */
class FetchBanksRequest extends AbstractRequest
{
    public function getData(): array
    {
        $this->validate('secretKey');

        return [];
    }

    protected function getHttpMethod(): string
    {
        return 'GET';
    }

    protected function getEndpoint(): string
    {
        return '/banks';
    }

    protected function createResponse(array $data): ResponseInterface
    {
        return $this->response = new FetchBanksResponse($this, $data);
    }
}
