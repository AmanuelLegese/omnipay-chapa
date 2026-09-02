<?php

namespace Omnipay\Chapa\Tests\Message;

use Omnipay\Chapa\Message\FetchBanksRequest;
use Omnipay\Chapa\Message\FetchBanksResponse;
use Omnipay\Chapa\Message\FetchTransferRequest;
use Omnipay\Chapa\Message\FetchTransferResponse;
use Omnipay\Chapa\Message\TransferRequest;
use Omnipay\Chapa\Message\TransferResponse;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Tests\TestCase;

class TransferRequestTest extends TestCase
{
    private function transfer(): TransferRequest
    {
        $request = new TransferRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize([
            'secretKey' => 'CHASECK_TEST-abc123',
            'amount' => '100.00',
            'accountNumber' => '1000212659000',
            'bankCode' => '946',
            'transactionId' => 'payout-0001',
        ]);

        return $request;
    }

    public function testBuildsPayoutPayload(): void
    {
        $this->assertSame([
            'account_number' => '1000212659000',
            'amount' => '100.00',
            'bank_code' => '946',
            'currency' => 'ETB',
            'reference' => 'payout-0001',
        ], $this->transfer()->getData());
    }

    public function testOmitsReferenceWhenUnset(): void
    {
        $request = new TransferRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize([
            'secretKey' => 'CHASECK_TEST-abc123',
            'amount' => '100.00',
            'accountNumber' => '1000212659000',
            'bankCode' => '946',
        ]);

        $this->assertArrayNotHasKey('reference', $request->getData());
    }

    public function testRequiresBankCode(): void
    {
        $request = new TransferRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize([
            'secretKey' => 'CHASECK_TEST-abc123',
            'amount' => '100.00',
            'accountNumber' => '1000212659000',
        ]);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('The bankCode parameter is required');

        $request->getData();
    }

    public function testPostsToTransfersEndpoint(): void
    {
        $request = $this->transfer();
        $this->setMockHttpResponse('TransferSuccess.txt');

        /** @var TransferResponse $response */
        $response = $request->send();

        $httpRequest = $this->getMockClient()->getLastRequest();
        $this->assertSame('POST', $httpRequest->getMethod());
        $this->assertSame('https://api.chapa.co/v1/transfers', (string) $httpRequest->getUri());

        $this->assertTrue($response->isSuccessful());
        // Chapa returns the payout reference as a bare string in `data`.
        $this->assertSame('3241342142sfdd', $response->getTransactionReference());
    }

    public function testReportsRejectedPayout(): void
    {
        $request = $this->transfer();
        $this->setMockHttpResponse('TransferFailure.txt');

        /** @var TransferResponse $response */
        $response = $request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertNull($response->getTransactionReference());
        $this->assertSame('bank_code: The selected bank code is invalid.', $response->getMessage());
    }

    public function testVerifiesPayout(): void
    {
        $request = new FetchTransferRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->setMockHttpResponse('FetchTransferSuccess.txt');
        $request->initialize([
            'secretKey' => 'CHASECK_TEST-abc123',
            'transactionId' => 'payout-0001',
        ]);

        /** @var FetchTransferResponse $response */
        $response = $request->send();

        $httpRequest = $this->getMockClient()->getLastRequest();
        $this->assertSame('GET', $httpRequest->getMethod());
        $this->assertSame(
            'https://api.chapa.co/v1/transfers/verify/payout-0001',
            (string) $httpRequest->getUri()
        );

        $this->assertTrue($response->isSuccessful());
        $this->assertFalse($response->isPending());
        $this->assertSame('payout-0001', $response->getTransactionReference());
        $this->assertSame('100', $response->getAmount());
        $this->assertSame('946', $response->getBankCode());
    }

    public function testPendingPayoutIsNotAFailure(): void
    {
        $request = new FetchTransferRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->setMockHttpResponse('FetchTransferPending.txt');
        $request->initialize([
            'secretKey' => 'CHASECK_TEST-abc123',
            'transactionId' => 'payout-0001',
        ]);

        /** @var FetchTransferResponse $response */
        $response = $request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isPending());
        $this->assertSame('pending', $response->getTransferStatus());
    }

    public function testListsBanksWithCodes(): void
    {
        $request = new FetchBanksRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->setMockHttpResponse('FetchBanksSuccess.txt');
        $request->initialize(['secretKey' => 'CHASECK_TEST-abc123']);

        /** @var FetchBanksResponse $response */
        $response = $request->send();

        $httpRequest = $this->getMockClient()->getLastRequest();
        $this->assertSame('GET', $httpRequest->getMethod());
        $this->assertSame('https://api.chapa.co/v1/banks', (string) $httpRequest->getUri());

        $this->assertTrue($response->isSuccessful());
        $this->assertCount(2, $response->getBanks());
        $this->assertSame([
            'Commercial Bank of Ethiopia (CBE)' => '946',
            'Awash Bank' => '855',
        ], $response->getBankCodes());
    }
}
