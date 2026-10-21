<?php

namespace Omnipay\Chapa\Tests\Message;

use Omnipay\Chapa\Message\PurchaseRequest;
use Omnipay\Chapa\Message\PurchaseResponse;
use Omnipay\Tests\TestCase;

class PurchaseResponseTest extends TestCase
{
    private function send(string $fixture): PurchaseResponse
    {
        // Build the request first: the mock client is created lazily by
        // getHttpClient(), and setMockHttpResponse() needs it to already exist.
        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->setMockHttpResponse($fixture);

        $request->initialize([
            'secretKey' => 'CHASECK_TEST-abc123',
            'amount' => '100.00',
            'currency' => 'ETB',
            'transactionId' => 'order-1234',
        ]);

        /** @var PurchaseResponse $response */
        $response = $request->send();

        return $response;
    }

    public function testSuccessfulInitializeIsARedirectNotAPayment(): void
    {
        $response = $this->send('PurchaseSuccess.txt');

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isRedirect());
        $this->assertSame('GET', $response->getRedirectMethod());
        $this->assertSame([], $response->getRedirectData());
        $this->assertSame(
            'https://checkout.chapa.co/checkout/payment/abc123',
            $response->getRedirectUrl()
        );
        $this->assertSame('Hosted Link', $response->getMessage());
        $this->assertSame('success', $response->getCode());
    }

    /**
     * Chapa reports validation failures with `message` as an object of
     * per-field errors rather than a string.
     */
    public function testFlattensFieldValidationErrors(): void
    {
        $response = $this->send('PurchaseFailure.txt');

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertNull($response->getRedirectUrl());
        $this->assertSame('failed', $response->getCode());
        // Chapa really does send the untranslated Laravel key here.
        $this->assertSame('amount: validation.required', $response->getMessage());
    }

    /**
     * A proxy or outage can answer with HTML, which decodes to null. That must
     * surface as a failure with a message rather than an empty response.
     */
    public function testNonJsonBodyBecomesAnError(): void
    {
        $response = $this->send('InvalidResponse.txt');

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertSame('failed', $response->getCode());
        $this->assertSame('Invalid response from Chapa (HTTP 502)', $response->getMessage());
    }
}
