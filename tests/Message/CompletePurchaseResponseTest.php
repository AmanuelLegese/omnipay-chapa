<?php

namespace Omnipay\Chapa\Tests\Message;

use Omnipay\Chapa\Message\CompletePurchaseRequest;
use Omnipay\Chapa\Message\CompletePurchaseResponse;
use Omnipay\Tests\TestCase;

/**
 * Every fixture here is a response captured from api.chapa.co in test mode.
 */
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
            'transactionId' => 'omnipay-chapa-probe-tx4-1789327377',
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
        $this->assertFalse($response->isFailed());
        $this->assertFalse($response->isRefunded());
        $this->assertSame('success', $response->getPaymentStatus());
        $this->assertSame('APjTAGhdvZTaI', $response->getTransactionReference());
        $this->assertSame('omnipay-chapa-probe-tx4-1789327377', $response->getTransactionId());
        $this->assertSame('ETB', $response->getCurrency());
        $this->assertSame('test', $response->getMode());
    }

    /**
     * Test mode reports `test` as the method whatever the customer used, so
     * the real values cannot be pinned until live.
     */
    public function testTestModeReportsMethodAsTest(): void
    {
        $this->assertSame('test', $this->send('CompletePurchaseSuccess.txt')->getPaymentMethod());
    }

    /**
     * Verify sends amounts as JSON numbers (int 10, float 0.25). They are
     * stringified verbatim rather than reformatted to two decimal places,
     * which would invent precision Chapa did not send.
     */
    public function testExposesAmountsVerbatim(): void
    {
        $response = $this->send('CompletePurchaseSuccess.txt');

        $this->assertSame('10', $response->getAmount());
        $this->assertSame('0.25', $response->getCharge());
    }

    /**
     * Chapa rewrites a local number to international form: the fixture was
     * initialized with 0900123456 and comes back as 251900123456.
     */
    public function testPhoneNumberComesBackInInternationalForm(): void
    {
        $this->assertSame('251900123456', $this->send('CompletePurchaseSuccess.txt')->getPhoneNumber());
    }

    /**
     * A pending transaction is not a failure - the envelope still says
     * success, and only data.status distinguishes the two. Before an attempt
     * there is no Chapa reference, charge or method yet.
     */
    public function testPendingIsNeitherSuccessNorFailure(): void
    {
        $response = $this->send('CompletePurchasePending.txt');

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isPending());
        $this->assertFalse($response->isFailed());
        $this->assertSame('success', $response->getCode());
        $this->assertNull($response->getTransactionReference());
        $this->assertNull($response->getCharge());
        $this->assertNull($response->getPaymentMethod());
    }

    public function testMetaRoundTrips(): void
    {
        $this->assertSame(
            ['order_id' => 'A-1', 'hide_receipt' => true, 'payment_reason' => 'probe'],
            $this->send('CompletePurchasePending.txt')->getMeta()
        );
    }

    /**
     * Chapa reports a failed or cancelled payment as the single literal string
     * `failed/cancelled`, slash included. Comparing against 'failed' misses it.
     */
    public function testCombinedFailedCancelledStatusIsAFailure(): void
    {
        $response = $this->send('CompletePurchaseFailedCancelled.txt');

        $this->assertSame('failed/cancelled', $response->getPaymentStatus());
        $this->assertTrue($response->isFailed());
        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isPending());
        // Chapa does not say which of the two happened.
        $this->assertFalse($response->isCancelled());
        // A failed attempt still gets a Chapa reference and a charge.
        $this->assertSame('APLZNciifiVuG', $response->getTransactionReference());
    }

    /**
     * The fixture is a 10 ETB payment of which only 5 ETB was refunded, yet
     * Chapa marks the whole transaction refunded. It must not read as a
     * payment that never happened.
     */
    public function testPartiallyRefundedPaymentReadsAsRefundedNotFailed(): void
    {
        $response = $this->send('CompletePurchaseRefunded.txt');

        $this->assertTrue($response->isRefunded());
        $this->assertFalse($response->isFailed());
        $this->assertFalse($response->isSuccessful());
        $this->assertSame('10', $response->getAmount());
    }

    public function testUnknownTransactionFails(): void
    {
        $response = $this->send('CompletePurchaseFailure.txt');

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isPending());
        $this->assertFalse($response->isFailed());
        $this->assertNull($response->getPaymentStatus());
        $this->assertNull($response->getTransactionReference());
        $this->assertSame('failed', $response->getCode());
        $this->assertSame('Invalid transaction reference', $response->getMessage());
    }
}
