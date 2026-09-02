<?php

namespace Omnipay\Chapa;

use Omnipay\Chapa\Message\CompletePurchaseRequest;
use Omnipay\Chapa\Message\FetchBanksRequest;
use Omnipay\Chapa\Message\FetchTransferRequest;
use Omnipay\Chapa\Message\Notification;
use Omnipay\Chapa\Message\PurchaseRequest;
use Omnipay\Chapa\Message\TransferRequest;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\Exception\InvalidRequestException;
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
            'webhookSecret' => '',
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

    /**
     * The secret Chapa signs webhooks with, set separately in the Chapa
     * dashboard. Falls back to the API secret key when left unset.
     */
    public function getWebhookSecret(): ?string
    {
        return $this->getParameter('webhookSecret') ?: $this->getSecretKey();
    }

    /**
     * @return $this
     */
    public function setWebhookSecret(?string $value): self
    {
        return $this->setParameter('webhookSecret', $value);
    }

    public function purchase(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(PurchaseRequest::class, $parameters);
    }

    public function completePurchase(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(CompletePurchaseRequest::class, $parameters);
    }

    /**
     * Verify and parse an incoming Chapa webhook.
     *
     * Unlike the request methods this returns the notification directly - there
     * is nothing to send(). It fails closed: an event whose payload signature
     * is missing or does not verify throws rather than returning an object,
     * so an unauthenticated event cannot be acted on by accident.
     *
     * @param array $parameters accepts `webhookSecret` to override the gateway's
     * @throws InvalidRequestException
     */
    public function acceptNotification(array $parameters = []): Notification
    {
        $secret = $parameters['webhookSecret'] ?? $this->getWebhookSecret();

        return new Notification($this->httpRequest, $secret);
    }

    /**
     * Send a payout to a bank account.
     *
     * Payouts are outside Omnipay's GatewayInterface, so this and the two
     * methods below are Chapa-specific and cannot be reached polymorphically.
     */
    public function transfer(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(TransferRequest::class, $parameters);
    }

    public function fetchTransfer(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(FetchTransferRequest::class, $parameters);
    }

    /**
     * List payout banks and their codes, which TransferRequest needs.
     */
    public function fetchBanks(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(FetchBanksRequest::class, $parameters);
    }
}
