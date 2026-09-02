<?php

namespace Omnipay\Chapa\Tests;

use Omnipay\Chapa\Gateway;
use Omnipay\Tests\GatewayTestCase;

class GatewayTest extends GatewayTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new Gateway($this->getHttpClient(), $this->getHttpRequest());
        $this->gateway->setSecretKey('CHASECK_TEST-abc123');
    }

    /**
     * Omnipay::create('Chapa') resolves Omnipay\Chapa\Gateway by naming
     * convention, so the short name has to stay 'Chapa'.
     */
    public function testResolvesByConventionalName(): void
    {
        $this->assertSame('Chapa', $this->gateway->getName());
        $this->assertSame('Chapa', $this->gateway->getShortName());
    }

    public function testSupportsHostedCheckoutOnly(): void
    {
        $this->assertTrue($this->gateway->supportsPurchase());
        $this->assertTrue($this->gateway->supportsCompletePurchase());

        $this->assertFalse($this->gateway->supportsAuthorize());
        $this->assertFalse($this->gateway->supportsCapture());
        $this->assertFalse($this->gateway->supportsRefund());
        $this->assertFalse($this->gateway->supportsVoid());
    }

    public function testPurchaseRedirectsToHostedCheckout(): void
    {
        $this->setMockHttpResponse('PurchaseSuccess.txt');

        $response = $this->gateway->purchase([
            'amount' => '100.00',
            'currency' => 'ETB',
            'transactionId' => 'order-1234',
        ])->send();

        // A redirect is never a completed payment.
        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isRedirect());
        $this->assertSame(
            'https://checkout.chapa.co/checkout/payment/abc123',
            $response->getRedirectUrl()
        );
    }

    public function testCompletePurchaseVerifiesTransaction(): void
    {
        $this->setMockHttpResponse('CompletePurchaseSuccess.txt');

        $response = $this->gateway->completePurchase([
            'transactionId' => 'chewatatest-6669',
        ])->send();

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('6jnheVKQEmy', $response->getTransactionReference());
    }
}
