<?php

namespace Omnipay\Chapa;

use Omnipay\Chapa\Message\CompletePurchaseRequest;
use Omnipay\Chapa\Message\PurchaseRequest;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\Message\AbstractRequest;

/**
 * Chapa gateway.
 *
 * Chapa is an Ethiopian payment gateway. The customer pays on Chapa's hosted
 * checkout, so purchase() only ever returns a redirect - the outcome is not
 * known until completePurchase() verifies the transaction server side.
 *
 * Test and live are selected by the secret key prefix (CHASECK_TEST- against
 * CHASECK-), not by a separate host, so testMode does not change the endpoint.
 *
 * @link https://developer.chapa.co
 */
class Gateway extends AbstractGateway
{
    public function getName(): string
    {
        return 'Chapa';
    }

    /**
     * `currency` is deliberately absent. Listing it here would break the
     * inherited GatewayTestCase parameter conformance checks, because the
     * request's setCurrency() upper-cases what the gateway stored verbatim.
     * PurchaseRequest applies the ETB default instead.
     */
    public function getDefaultParameters(): array
    {
        return [
            'secretKey' => '',
            'testMode' => false,
        ];
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

    public function purchase(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(PurchaseRequest::class, $parameters);
    }

    public function completePurchase(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(CompletePurchaseRequest::class, $parameters);
    }
}
