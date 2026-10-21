<?php

namespace Omnipay\Chapa\Tests\Message;

use Omnipay\Chapa\Message\FetchRefundRequest;
use Omnipay\Chapa\Message\FetchRefundResponse;
use Omnipay\Chapa\Message\RefundRequest;
use Omnipay\Chapa\Message\RefundResponse;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Tests\TestCase;

/**
 * Every fixture here is a response captured from api.chapa.co in test mode:
 * a 5 ETB partial refund of a 10 ETB card payment.
 */
class RefundRequestTest extends TestCase
{
    private function refund(array $overrides = []): RefundRequest
    {
        $request = new RefundRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize(array_merge([
            'secretKey' => 'CHASECK_TEST-abc123',
            'transactionReference' => 'APjTAGhdvZTaI',
        ], $overrides));

        return $request;
    }

    private function sendRefund(string $fixture, array $overrides = []): RefundResponse
    {
        $request = $this->refund($overrides);
        $this->setMockHttpResponse($fixture);

        /** @var RefundResponse $response */
        $response = $request->send();

        return $response;
    }

    /**
     * Chapa refunds everything when no amount is sent, so an unset amount must
     * be absent from the payload rather than sent as null or zero.
     */
    public function testFullRefundSendsNoAmount(): void
    {
        $this->assertSame([], $this->refund()->getData());
    }

    public function testPartialRefundPayload(): void
    {
        $this->assertSame([
            'amount' => '5.00',
            'reason' => 'omnipay-chapa probe',
            'reference' => 'ocp-refund-1',
            'meta' => ['ticket' => 'T-9'],
        ], $this->refund([
            'amount' => '5.00',
            'reason' => 'omnipay-chapa probe',
            'refundReference' => 'ocp-refund-1',
            'meta' => ['ticket' => 'T-9'],
        ])->getData());
    }

    public function testReasonFallsBackToDescription(): void
    {
        $this->assertSame('Damaged item', $this->refund(['description' => 'Damaged item'])->getData()['reason']);
    }

    /**
     * Confirmed against the API: the path takes Chapa's reference. The merchant
     * tx_ref returns "Transaction not found".
     */
    public function testPostsToRefundEndpointUsingChapaReference(): void
    {
        $this->sendRefund('RefundSuccess.txt');

        $httpRequest = $this->getMockClient()->getLastRequest();
        $this->assertSame('POST', $httpRequest->getMethod());
        $this->assertSame('https://api.chapa.co/v1/refund/APjTAGhdvZTaI', (string) $httpRequest->getUri());
    }

    /**
     * Passing the tx_ref where Chapa's reference belongs is the natural mistake,
     * and the API punishes it with an unhelpful "not found". Say what is wrong.
     */
    public function testExplainsWhenTxRefIsGivenInsteadOfChapaReference(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage("Refunds need Chapa's transactionReference");

        $this->refund(['transactionReference' => null, 'transactionId' => 'order-1234'])->getData();
    }

    public function testRequiresTransactionReference(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('The transactionReference parameter is required');

        $this->refund(['transactionReference' => null])->getData();
    }

    public function testReadsInitiatedRefund(): void
    {
        $response = $this->sendRefund('RefundSuccess.txt');

        $this->assertTrue($response->isSuccessful());
        $this->assertTrue($response->isPending());
        $this->assertSame('initiated', $response->getRefundStatus());
        $this->assertSame('TEST-DIS-REF-hiZb0ylpDzo', $response->getRefundId());
        $this->assertSame('TEST-DIS-REF-hiZb0ylpDzo', $response->getTransactionReference());
        $this->assertSame('APjTAGhdvZTaI', $response->getPaymentReference());
        $this->assertSame('ocp-refund-1', $response->getMerchantReference());
        $this->assertSame('5', $response->getAmount());
    }

    /**
     * A reused refundReference is refused, which is what makes it an
     * idempotency key for retries.
     */
    public function testReusedRefundReferenceIsRejected(): void
    {
        $response = $this->sendRefund('RefundDuplicateReference.txt');

        $this->assertFalse($response->isSuccessful());
        $this->assertNull($response->getRefundId());
        $this->assertSame('A refund has been initiated with this reference before.', $response->getMessage());
    }

    public function testUnknownPaymentIsRejected(): void
    {
        $response = $this->sendRefund('RefundTransactionNotFound.txt');

        $this->assertFalse($response->isSuccessful());
        $this->assertSame(
            'We cannot process refund for this transaction. Transaction not found.',
            $response->getMessage()
        );
    }

    public function testFetchesRefundProgress(): void
    {
        $request = new FetchRefundRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->setMockHttpResponse('FetchRefundSuccess.txt');
        $request->initialize([
            'secretKey' => 'CHASECK_TEST-abc123',
            'refundId' => 'TEST-DIS-REF-hiZb0ylpDzo',
        ]);

        /** @var FetchRefundResponse $response */
        $response = $request->send();

        $httpRequest = $this->getMockClient()->getLastRequest();
        $this->assertSame('GET', $httpRequest->getMethod());
        $this->assertSame(
            'https://api.chapa.co/v1/refund/TEST-DIS-REF-hiZb0ylpDzo/verify',
            (string) $httpRequest->getUri()
        );

        $this->assertTrue($response->isSuccessful());
        $this->assertFalse($response->isPending());
        $this->assertFalse($response->isReversed());
        $this->assertSame('refunded', $response->getRefundStatus());
        $this->assertSame('APjTAGhdvZTaI', $response->getPaymentReference());
        // Verify returns the amount as a string, where initiate returned a number.
        $this->assertSame('5.00', $response->getAmount());
    }
}
