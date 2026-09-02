<?php

namespace Omnipay\Chapa\Tests\Message;

use Omnipay\Chapa\Message\CompletePurchaseRequest;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Tests\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

class CompletePurchaseRequestTest extends TestCase
{
    private CompletePurchaseRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = new CompletePurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->request->initialize([
            'secretKey' => 'CHASECK_TEST-abc123',
            'transactionId' => 'chewatatest-6669',
        ]);
    }

    public function testVerifyCarriesNoBody(): void
    {
        $this->assertSame([], $this->request->getData());
    }

    public function testSendsAuthorisedGetToVerifyEndpoint(): void
    {
        $this->setMockHttpResponse('CompletePurchaseSuccess.txt');

        $this->request->send();

        $httpRequest = $this->getMockClient()->getLastRequest();

        $this->assertSame('GET', $httpRequest->getMethod());
        $this->assertSame(
            'https://api.chapa.co/v1/transaction/verify/chewatatest-6669',
            (string) $httpRequest->getUri()
        );
        $this->assertSame(
            'Bearer CHASECK_TEST-abc123',
            $httpRequest->getHeaderLine('Authorization')
        );
        $this->assertSame('', (string) $httpRequest->getBody());
    }

    /**
     * Merchants choose their own tx_ref, so one containing a slash or a space
     * must not be able to reshape the request path.
     */
    public function testEscapesTransactionIdInPath(): void
    {
        $this->setMockHttpResponse('CompletePurchaseSuccess.txt');

        $this->request->setTransactionId('order/1234 5678');
        $this->request->send();

        $this->assertSame(
            'https://api.chapa.co/v1/transaction/verify/order%2F1234%205678',
            (string) $this->getMockClient()->getLastRequest()->getUri()
        );
    }

    public function testRequiresTransactionId(): void
    {
        $request = new CompletePurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize(['secretKey' => 'CHASECK_TEST-abc123']);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('The transactionId parameter is required');

        $request->getData();
    }

    /**
     * Chapa's callback carries `trx_ref`, while the initialize payload field is
     * `tx_ref`. The spellings differ by one character and chapa-laravel reads
     * `request()->trx_ref`; reading the wrong one silently yields null.
     */
    public function testReadsTrxRefFromTheIncomingRequest(): void
    {
        $request = new CompletePurchaseRequest(
            $this->getHttpClient(),
            new HttpRequest(['trx_ref' => 'order-1234', 'status' => 'success'])
        );
        $request->initialize(['secretKey' => 'CHASECK_TEST-abc123']);

        $this->assertSame('order-1234', $request->getTransactionId());
    }

    public function testFallsBackToTxRefSpelling(): void
    {
        $request = new CompletePurchaseRequest(
            $this->getHttpClient(),
            new HttpRequest(['tx_ref' => 'order-5678'])
        );
        $request->initialize(['secretKey' => 'CHASECK_TEST-abc123']);

        $this->assertSame('order-5678', $request->getTransactionId());
    }

    public function testReadsTrxRefFromAPostedCallbackBody(): void
    {
        $request = new CompletePurchaseRequest(
            $this->getHttpClient(),
            new HttpRequest([], ['trx_ref' => 'order-9012'])
        );
        $request->initialize(['secretKey' => 'CHASECK_TEST-abc123']);

        $this->assertSame('order-9012', $request->getTransactionId());
    }

    /**
     * getTransactionId() alone is not enough: validate() reads the parameter
     * bag directly, so a reference that only exists on the request must still
     * satisfy validation and reach the URL.
     */
    public function testVerifiesUsingOnlyTheReferenceFromTheRequest(): void
    {
        $request = new CompletePurchaseRequest(
            $this->getHttpClient(),
            new HttpRequest(['trx_ref' => 'order-1234'])
        );
        $this->setMockHttpResponse('CompletePurchaseSuccess.txt');
        $request->initialize(['secretKey' => 'CHASECK_TEST-abc123']);

        $request->send();

        $this->assertSame(
            'https://api.chapa.co/v1/transaction/verify/order-1234',
            (string) $this->getMockClient()->getLastRequest()->getUri()
        );
    }

    /**
     * The request is not a trusted source, so an explicit value must win.
     */
    public function testExplicitTransactionIdOverridesTheRequest(): void
    {
        $request = new CompletePurchaseRequest(
            $this->getHttpClient(),
            new HttpRequest(['trx_ref' => 'from-request'])
        );
        $request->initialize([
            'secretKey' => 'CHASECK_TEST-abc123',
            'transactionId' => 'explicit',
        ]);

        $this->assertSame('explicit', $request->getTransactionId());
    }
}
