<?php

namespace Omnipay\Chapa\Tests\Message;

use Omnipay\Chapa\Message\VoidRequest;
use Omnipay\Chapa\Message\VoidResponse;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Tests\TestCase;

/**
 * Every fixture here is a response captured from api.chapa.co in test mode.
 */
class VoidRequestTest extends TestCase
{
    private function send(string $fixture): VoidResponse
    {
        $request = new VoidRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->setMockHttpResponse($fixture);
        $request->initialize([
            'secretKey' => 'CHASECK_TEST-abc123',
            'transactionId' => 'ocp-void-1789327715',
        ]);

        /** @var VoidResponse $response */
        $response = $request->send();

        return $response;
    }

    public function testCancelsCheckoutWithPutToCancelEndpoint(): void
    {
        $response = $this->send('VoidSuccess.txt');

        $httpRequest = $this->getMockClient()->getLastRequest();
        $this->assertSame('PUT', $httpRequest->getMethod());
        $this->assertSame(
            'https://api.chapa.co/v1/transaction/cancel/ocp-void-1789327715',
            (string) $httpRequest->getUri()
        );
        $this->assertSame('', (string) $httpRequest->getBody());

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('ocp-void-1789327715', $response->getTransactionId());
        $this->assertSame('Checkout link expired successfully', $response->getMessage());
    }

    public function testCancellingTwiceFails(): void
    {
        $response = $this->send('VoidAlreadyExpired.txt');

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('Payment link already expired', $response->getMessage());
    }

    public function testUnknownTransactionFails(): void
    {
        $response = $this->send('VoidNotFound.txt');

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('Payment link not found', $response->getMessage());
    }

    public function testRequiresTransactionId(): void
    {
        $request = new VoidRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize(['secretKey' => 'CHASECK_TEST-abc123']);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('The transactionId parameter is required');

        $request->getData();
    }
}
