<?php

namespace Omnipay\Chapa\Tests\Message;

use Omnipay\Chapa\Message\PurchaseRequest;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Tests\TestCase;

class PurchaseRequestTest extends TestCase
{
    private PurchaseRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->request->initialize([
            'secretKey' => 'CHASECK_TEST-abc123',
            'amount' => '100.00',
            'currency' => 'ETB',
            'transactionId' => 'order-1234',
        ]);
    }

    public function testBuildsMinimalPayload(): void
    {
        $this->assertSame([
            'amount' => '100.00',
            'currency' => 'ETB',
            'tx_ref' => 'order-1234',
        ], $this->request->getData());
    }

    public function testDefaultsToEthiopianBirr(): void
    {
        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize([
            'secretKey' => 'CHASECK_TEST-abc123',
            'amount' => '100.00',
            'transactionId' => 'order-1234',
        ]);

        $this->assertSame('ETB', $request->getData()['currency']);
    }

    /**
     * returnUrl is where the browser lands; notifyUrl is Chapa's server-to-server
     * callback. Swapping these silently breaks reconciliation, so pin them.
     */
    public function testMapsReturnUrlAndNotifyUrlToTheRightFields(): void
    {
        $this->request->setReturnUrl('https://example.com/return');
        $this->request->setNotifyUrl('https://example.com/webhook');

        $data = $this->request->getData();

        $this->assertSame('https://example.com/return', $data['return_url']);
        $this->assertSame('https://example.com/webhook', $data['callback_url']);
    }

    public function testSendsCustomerIdentityFromCard(): void
    {
        $this->request->setCard([
            'firstName' => 'Bilen',
            'lastName' => 'Gizachew',
            'email' => 'bilen@example.com',
            'phone' => '0911223344',
        ]);

        $data = $this->request->getData();

        $this->assertSame('Bilen', $data['first_name']);
        $this->assertSame('Gizachew', $data['last_name']);
        $this->assertSame('bilen@example.com', $data['email']);
        $this->assertSame('0911223344', $data['phone_number']);
    }

    public function testOmitsEmptyCardFields(): void
    {
        $this->request->setCard(['firstName' => 'Bilen']);

        $data = $this->request->getData();

        $this->assertSame('Bilen', $data['first_name']);
        $this->assertArrayNotHasKey('email', $data);
        $this->assertArrayNotHasKey('phone_number', $data);
    }

    public function testCustomizationDescriptionFallsBackToDescription(): void
    {
        $this->request->setCustomizationTitle('Example Store');
        $this->request->setDescription('Order 1234');

        $this->assertSame(
            ['title' => 'Example Store', 'description' => 'Order 1234'],
            $this->request->getData()['customization']
        );
    }

    public function testOmitsCustomizationWhenUnset(): void
    {
        $this->assertArrayNotHasKey('customization', $this->request->getData());
    }

    public function testRequiresSecretKey(): void
    {
        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize(['amount' => '100.00', 'transactionId' => 'order-1234']);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('The secretKey parameter is required');

        $request->getData();
    }

    public function testRequiresTransactionId(): void
    {
        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize(['secretKey' => 'CHASECK_TEST-abc123', 'amount' => '100.00']);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('The transactionId parameter is required');

        $request->getData();
    }

    public function testSendsAuthorisedPostToInitializeEndpoint(): void
    {
        $this->setMockHttpResponse('PurchaseSuccess.txt');

        $this->request->send();

        $httpRequest = $this->getMockClient()->getLastRequest();

        $this->assertSame('POST', $httpRequest->getMethod());
        $this->assertSame(
            'https://api.chapa.co/v1/transaction/initialize',
            (string) $httpRequest->getUri()
        );
        $this->assertSame(
            'Bearer CHASECK_TEST-abc123',
            $httpRequest->getHeaderLine('Authorization')
        );
        $this->assertSame(
            ['amount' => '100.00', 'currency' => 'ETB', 'tx_ref' => 'order-1234'],
            json_decode((string) $httpRequest->getBody(), true)
        );
    }
}
