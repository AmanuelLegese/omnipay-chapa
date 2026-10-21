<?php

namespace Omnipay\Chapa\Message;

use Omnipay\Common\Message\ResponseInterface;

/**
 * Verify a Chapa transaction when the customer returns from checkout.
 *
 * The redirect back from the hosted checkout carries no trustworthy outcome,
 * so the payment status must be read from this server-side call. It is the
 * same verify as fetchTransaction(), plus reading the reference off the
 * incoming request when none was passed.
 *
 * @link https://developer.chapa.co/integrations/verify-payments
 */
class CompletePurchaseRequest extends FetchTransactionRequest
{
    /**
     * Chapa's callback carries `trx_ref`, not the `tx_ref` spelling used in the
     * initialize payload. Both are accepted here so a caller who redirects
     * through either does not have to know which one arrived.
     */
    private const REFERENCE_KEYS = ['trx_ref', 'tx_ref'];

    /**
     * Falls back to the reference on the incoming request, which is what Chapa
     * appends when returning the customer. An explicitly set transactionId
     * always wins - the request is attacker-controlled and only ever selects
     * which transaction to verify, never what its outcome was.
     */
    public function getTransactionId(): ?string
    {
        $explicit = parent::getTransactionId();

        if ($explicit !== null && $explicit !== '') {
            return (string) $explicit;
        }

        foreach (self::REFERENCE_KEYS as $key) {
            foreach ([$this->httpRequest->query, $this->httpRequest->request] as $bag) {
                $value = $bag->get($key);

                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    protected function createResponse(array $data): ResponseInterface
    {
        return $this->response = new CompletePurchaseResponse($this, $data);
    }
}
