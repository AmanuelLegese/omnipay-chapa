<?php

namespace Omnipay\Chapa\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Refund a payment, fully or in part.
 *
 * ## The path takes Chapa's reference, not your tx_ref
 *
 * Confirmed against the API: POST /refund/{your tx_ref} answers "Transaction not
 * found", while POST /refund/{Chapa's reference} works. That reference is what
 * fetchTransaction() / completePurchase() return from getTransactionReference(),
 * and is Omnipay's `transactionReference`.
 *
 * ## Refunds are not idempotent unless you make them so
 *
 * Chapa rejects a second refund carrying the same `refundReference` ("A refund
 * has been initiated with this reference before"). Set one derived from your
 * own records and a retried request cannot refund twice.
 *
 * Any refund, partial or full, flips the payment's verify status to `refunded`.
 *
 * @link https://developer.chapa.co/refund
 */
class RefundRequest extends AbstractRequest
{
    public function getReason(): ?string
    {
        return $this->getParameter('reason') ?: $this->getDescription();
    }

    /**
     * Why the refund is being made. Falls back to Omnipay's `description`.
     *
     * @return $this
     */
    public function setReason(?string $value): self
    {
        return $this->setParameter('reason', $value);
    }

    public function getRefundReference(): ?string
    {
        return $this->getParameter('refundReference');
    }

    /**
     * Your own unique id for this refund. Chapa refuses to reuse one, which
     * makes it the idempotency key.
     *
     * @return $this
     */
    public function setRefundReference(?string $value): self
    {
        return $this->setParameter('refundReference', $value);
    }

    public function getMeta(): ?array
    {
        return $this->getParameter('meta');
    }

    /**
     * Echoed back on the refund and its webhook.
     *
     * @return $this
     */
    public function setMeta(?array $value): self
    {
        return $this->setParameter('meta', $value);
    }

    public function getData(): array
    {
        $this->validate('secretKey');

        if (($this->getTransactionReference() ?? '') === '') {
            // The mistake the API punishes with an unhelpful "not found":
            // passing the merchant tx_ref where Chapa's reference belongs.
            throw new InvalidRequestException(
                ($this->getTransactionId() ?? '') !== ''
                    ? 'Refunds need Chapa\'s transactionReference (from fetchTransaction()->getTransactionReference()), '
                        . 'not your transactionId'
                    : 'The transactionReference parameter is required'
            );
        }

        // Chapa refunds the full amount when none is sent.
        $data = array_filter([
            'amount' => $this->getParameter('amount') !== null ? $this->getAmount() : null,
            'reason' => $this->getReason(),
            'reference' => $this->getRefundReference(),
        ], static fn ($value): bool => $value !== null && $value !== '');

        if ($meta = $this->getMeta()) {
            $data['meta'] = $meta;
        }

        return $data;
    }

    protected function getEndpoint(): string
    {
        return '/refund/' . rawurlencode((string) $this->getTransactionReference());
    }

    protected function createResponse(array $data): ResponseInterface
    {
        return $this->response = new RefundResponse($this, $data);
    }
}
