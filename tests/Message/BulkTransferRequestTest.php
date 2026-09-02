<?php

namespace Omnipay\Chapa\Tests\Message;

use Omnipay\Chapa\Message\BulkTransferRequest;
use Omnipay\Chapa\Message\BulkTransferResponse;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Tests\TestCase;

class BulkTransferRequestTest extends TestCase
{
    private const ROW = [
        'account_name' => 'Israel Goytom',
        'account_number' => '1000212659000',
        'amount' => 100,
        'reference' => 'payout-0001',
        'bank_code' => 946,
    ];

    private function bulk(array $rows = [self::ROW]): BulkTransferRequest
    {
        $request = new BulkTransferRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize([
            'secretKey' => 'CHASECK_TEST-abc123',
            'title' => 'January payouts',
            'bulkData' => $rows,
        ]);

        return $request;
    }

    public function testBuildsBulkPayload(): void
    {
        $this->assertSame([
            'title' => 'January payouts',
            'currency' => 'ETB',
            'bulk_data' => [self::ROW],
        ], $this->bulk()->getData());
    }

    /**
     * Rows may arrive from a filtered array with gaps in its keys. json_encode
     * would turn that into an object rather than a list, which Chapa rejects.
     */
    public function testReindexesSparseRowArrays(): void
    {
        $rows = [3 => self::ROW, 7 => self::ROW];

        $data = $this->bulk($rows)->getData();

        $this->assertSame([0, 1], array_keys($data['bulk_data']));
    }

    /**
     * A row missing a field is a payout that goes somewhere unintended, so it
     * has to fail before the batch is sent rather than partway through it.
     */
    public function testRejectsRowMissingARequiredField(): void
    {
        $incomplete = self::ROW;
        unset($incomplete['bank_code']);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('bulkData row 1 is missing the bank_code field');

        $this->bulk([self::ROW, $incomplete])->getData();
    }

    public function testRejectsEmptyBatch(): void
    {
        $request = new BulkTransferRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize([
            'secretKey' => 'CHASECK_TEST-abc123',
            'title' => 'January payouts',
            'bulkData' => [],
        ]);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('The bulkData parameter must not be empty');

        $request->getData();
    }

    public function testPostsToBulkTransfersEndpoint(): void
    {
        $request = $this->bulk();
        $this->setMockHttpResponse('BulkTransferSuccess.txt');

        /** @var BulkTransferResponse $response */
        $response = $request->send();

        $httpRequest = $this->getMockClient()->getLastRequest();
        $this->assertSame('POST', $httpRequest->getMethod());
        $this->assertSame('https://api.chapa.co/v1/bulk-transfers', (string) $httpRequest->getUri());

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('1234', $response->getBulkId());
    }
}
