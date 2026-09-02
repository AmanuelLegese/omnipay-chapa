<?php

namespace Omnipay\Chapa\Message;

use Omnipay\Common\Message\AbstractRequest as OmnipayAbstractRequest;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Shared behaviour for every Chapa request: authentication, JSON transport,
 * and turning a raw HTTP response into a Chapa response object.
 */
abstract class AbstractRequest extends OmnipayAbstractRequest
{
    /**
     * Chapa exposes one host for both test and live; the secret key prefix
     * decides which mode a request runs in.
     */
    protected const API_BASE = 'https://api.chapa.co/v1';

    /**
     * Path appended to the API base, with a leading slash.
     */
    abstract protected function getEndpoint(): string;

    abstract protected function createResponse(array $data): ResponseInterface;

    protected function getHttpMethod(): string
    {
        return 'POST';
    }

    protected function getBaseUrl(): string
    {
        return static::API_BASE;
    }

    public function getSecretKey(): ?string
    {
        return $this->getParameter('secretKey');
    }

    /**
     * @return $this
     */
    public function setSecretKey(?string $value): self
    {
        return $this->setParameter('secretKey', $value);
    }

    /**
     * @param array $data
     */
    public function sendData($data): ResponseInterface
    {
        $httpResponse = $this->httpClient->request(
            $this->getHttpMethod(),
            $this->getBaseUrl() . $this->getEndpoint(),
            [
                'Authorization' => 'Bearer ' . $this->getSecretKey(),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            $data === [] ? null : json_encode($data)
        );

        $payload = json_decode((string) $httpResponse->getBody(), true);

        // An outage or a proxy can answer with HTML or an empty body. Decoding
        // that yields null, which would otherwise surface as a silent failure
        // with no message, so synthesise an error payload instead.
        if (!is_array($payload)) {
            $payload = [
                'status' => 'failed',
                'message' => sprintf(
                    'Invalid response from Chapa (HTTP %d)',
                    $httpResponse->getStatusCode()
                ),
                'data' => null,
            ];
        }

        return $this->createResponse($payload);
    }
}
