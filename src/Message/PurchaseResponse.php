<?php

namespace Omnipay\Chapa\Message;

use Omnipay\Common\Message\RedirectResponseInterface;

/**
 * Response to a transaction initialize.
 *
 * On success this is always a redirect to Chapa's hosted checkout. It is never
 * a completed payment, so isSuccessful() is false even when Chapa accepted the
 * request - call completePurchase() after the customer returns.
 */
class PurchaseResponse extends AbstractResponse implements RedirectResponseInterface
{
    public function isSuccessful(): bool
    {
        return false;
    }

    public function isRedirect(): bool
    {
        return $this->getRedirectUrl() !== null;
    }

    public function getRedirectUrl(): ?string
    {
        return $this->getPaymentValue('checkout_url');
    }

    public function getRedirectMethod(): string
    {
        return 'GET';
    }

    public function getRedirectData(): array
    {
        return [];
    }
}
