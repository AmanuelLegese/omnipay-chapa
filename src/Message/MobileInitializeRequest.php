<?php

namespace Omnipay\Chapa\Message;

/**
 * Initialize a transaction against Chapa's mobile endpoint.
 *
 * Same payload and response as PurchaseRequest; only the endpoint differs.
 *
 * @link https://developer.chapa.co
 */
class MobileInitializeRequest extends PurchaseRequest
{
    protected function getEndpoint(): string
    {
        return '/transaction/mobile-initialize';
    }
}
