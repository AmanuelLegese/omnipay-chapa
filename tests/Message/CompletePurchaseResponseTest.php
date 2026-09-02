<?php

namespace Omnipay\Chapa\Tests\Message;

use Omnipay\Chapa\Message\CompletePurchaseRequest;
use Omnipay\Chapa\Message\CompletePurchaseResponse;
use Omnipay\Tests\TestCase;

class CompletePurchaseResponseTest extends TestCase
{
    private function send(string $fixture): CompletePurchaseResponse
    {
        // Build the request first: the mock client is created lazily by
        // getHttpClient(), and setMockHttpResponse() needs it to already exist.
        $request = new CompletePurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->setMockHttpResponse($fixture);

        $request->initialize([
            'secretKey' => 'CHASECK_TEST-abc123',
            'transactionId' => 'chewatatest-6669',
        ]);

        /** @var CompletePurchaseResponse $response */
        $response = $request->send();

        return $response;
    }

    public function testReadsSettledPayment(): void
    {
        $response = $this->send('CompletePurchaseSuccess.txt');

        $this->assertTrue($response->isSuccessful());
        $this->assertFalse($response->isPending());
        $this->assertSame('success', $response->getPaymentStatus());
        $this->assertSame('6jnheVKQEmy', $response->getTransactionReference());
        $this->assertSame('chewatatest-6669', $response->getTransactionId());
        $this->assertSame('ETB', $response->getCurrency());
        $this->assertSame('telebirr', $response->getPaymentMethod());
        $this->assertSame('test', $response->getMode());
    }

    /**
     * Chapa sends amounts as JSON numbers, so they arrive as int/float. They
     * are stringified verbatim rather than reformatted to 2 decimal places,
     * which would invent precision Chapa did not send.
     */
    public function testExposesAmountsVerbatim(): void
    {
        $response = $this->send('CompletePurchaseSuccess.txt');

        $this->assertSame('100', $response->getAmount());
        $this->assertSame('3.5', $response->getCharge());
    }

    /**
     * A pending transaction is not a failure - the envelope still says
     * success, and only data.status distinguishes the two.
     */
    public function testPendingIsNeitherSuccessNorFailure(): void
    {
        $response = $this->send('CompletePurchasePending.txt');

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isPending());
        $this->assertSame('pending', $response->getPaymentStatus());
        $this->assertSame('success', $response->getCode());
    }

    public function testUnknownTransactionFails(): void
    {
        $response = $this->send('CompletePurchaseFailure.txt');

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isPending());
        $this->assertNull($response->getPaymentStatus());
        $this->assertNull($response->getTransactionReference());
        $this->assertSame('failed', $response->getCode());
        $this->assertSame('Transaction not found.', $response->getMessage());
    }
}
