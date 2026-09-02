<?php

namespace Omnipay\Chapa\Tests\Message;

use Omnipay\Chapa\Message\CompletePurchaseRequest;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Tests\TestCase;

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
}
