<?php

namespace Omnipay\Chapa\Tests\Message;

use Omnipay\Chapa\Message\FetchTransactionRequest;
use Omnipay\Chapa\Message\FetchTransactionResponse;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Tests\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

class FetchTransactionRequestTest extends TestCase
{
    public function testVerifiesByExplicitTransactionId(): void
    {
        $request = new FetchTransactionRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->setMockHttpResponse('CompletePurchaseSuccess.txt');
        $request->initialize([
            'secretKey' => 'CHASECK_TEST-abc123',
            'transactionId' => 'omnipay-chapa-probe-tx4-1789327377',
        ]);

        $response = $request->send();

        $this->assertSame(
            'https://api.chapa.co/v1/transaction/verify/omnipay-chapa-probe-tx4-1789327377',
            (string) $this->getMockClient()->getLastRequest()->getUri()
        );
        $this->assertInstanceOf(FetchTransactionResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
    }

    /**
     * Unlike completePurchase(), fetchTransaction() is called from places with
     * no customer request - a cron job, an admin screen - so it must never pick
     * a reference up from whatever request happens to be in flight.
     */
    public function testDoesNotReadTheReferenceFromTheIncomingRequest(): void
    {
        $request = new FetchTransactionRequest(
            $this->getHttpClient(),
            new HttpRequest(['trx_ref' => 'from-request'])
        );
        $request->initialize(['secretKey' => 'CHASECK_TEST-abc123']);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('The transactionId parameter is required');

        $request->getData();
    }
}
