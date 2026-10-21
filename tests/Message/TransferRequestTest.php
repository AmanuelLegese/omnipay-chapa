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

/**
 * Transfer, bank-list and test-mode transfer-verify fixtures are captured from
 * api.chapa.co. FetchTransferSuccess/Pending are built from Chapa's documented
 * examples, because test mode never returns transfer details to verify.
 */
class TransferRequestTest extends TestCase
{
    private function transfer(array $overrides = []): TransferRequest
    {
        $request = new TransferRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize(array_merge([
            'secretKey' => 'CHASECK_TEST-abc123',
            'amount' => '10.00',
            'accountNumber' => '0900123456',
            'bankCode' => '128',
            'transactionId' => 'ocp-probe-1789325788',
        ], $overrides));

        return $request;
    }

    private function fetchTransfer(string $fixture): FetchTransferResponse
    {
        $request = new FetchTransferRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->setMockHttpResponse($fixture);
        $request->initialize([
            'secretKey' => 'CHASECK_TEST-abc123',
            'transactionId' => 'payout-0001',
        ]);

        /** @var FetchTransferResponse $response */
        $response = $request->send();

        return $response;
    }

    private function fetchBanks(): FetchBanksResponse
    {
        $request = new FetchBanksRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->setMockHttpResponse('FetchBanksSuccess.txt');
        $request->initialize(['secretKey' => 'CHASECK_TEST-abc123']);

        /** @var FetchBanksResponse $response */
        $response = $request->send();

        return $response;
    }

    public function testBuildsPayoutPayload(): void
    {
        $this->assertSame([
            'account_number' => '0900123456',
            'amount' => '10.00',
            'bank_code' => '128',
            'currency' => 'ETB',
            'reference' => 'ocp-probe-1789325788',
        ], $this->transfer()->getData());
    }

    public function testOmitsReferenceWhenUnset(): void
    {
        $this->assertArrayNotHasKey('reference', $this->transfer(['transactionId' => null])->getData());
    }

    public function testRequiresBankCode(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('The bankCode parameter is required');

        $this->transfer(['bankCode' => null])->getData();
    }

    /**
     * Chapa rejects references over 36 characters. Refusing locally means the
     * payout is never sent rather than sent and bounced.
     */
    public function testRejectsReferenceLongerThanChapaAccepts(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('36 characters or fewer for a transfer; got 37');

        $this->transfer(['transactionId' => str_repeat('x', 37)])->getData();
    }

    public function testAcceptsReferenceAtTheLimit(): void
    {
        $reference = str_repeat('x', 36);

        $this->assertSame($reference, $this->transfer(['transactionId' => $reference])->getData()['reference']);
    }

    public function testTestStatusForcesTheOutcomeWithATestKey(): void
    {
        $this->assertSame('pending', $this->transfer(['testStatus' => 'pending'])->getData()['status']);
    }

    /**
     * A forced outcome is only meaningful in test mode. Sending it with a live
     * key is a mistake worth stopping before money moves.
     */
    public function testTestStatusIsRefusedWithALiveKey(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('testStatus can only be used with a CHASECK_TEST- secret key');

        $this->transfer(['secretKey' => 'CHASECK-live123', 'testStatus' => 'success'])->getData();
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
        // Chapa echoes the merchant's reference back as a bare string in `data`.
        $this->assertSame('ocp-probe-1789325788', $response->getTransactionReference());
    }

    public function testReportsRejectedPayout(): void
    {
        $request = $this->transfer();
        $this->setMockHttpResponse('TransferFailure.txt');

        /** @var TransferResponse $response */
        $response = $request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertNull($response->getTransactionReference());
        $this->assertSame('reference: The reference field need to be 36 characters or less', $response->getMessage());
    }

    public function testVerifiesPayout(): void
    {
        $response = $this->fetchTransfer('FetchTransferSuccess.txt');

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
        $response = $this->fetchTransfer('FetchTransferPending.txt');

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isPending());
        $this->assertSame('pending', $response->getTransferStatus());
    }

    /**
     * Test mode answers every transfer verify with `data: [null]` and an
     * envelope status of success. That must read as "no details", not as a
     * successful payout, and must not break on the list shape.
     */
    public function testTestModeTransferVerifyIsNotReadAsSuccess(): void
    {
        $response = $this->fetchTransfer('FetchTransferTestMode.txt');

        $this->assertSame('success', $response->getCode());
        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isPending());
        $this->assertNull($response->getTransferStatus());
        $this->assertNull($response->getTransactionReference());
    }

    /**
     * The real bank list is the one Chapa endpoint whose success envelope has
     * no `status` field. Reading success from `status` reported every
     * successful bank list as a failure.
     */
    public function testBankListWithoutStatusFieldIsSuccessful(): void
    {
        $response = $this->fetchBanks();

        $httpRequest = $this->getMockClient()->getLastRequest();
        $this->assertSame('GET', $httpRequest->getMethod());
        $this->assertSame('https://api.chapa.co/v1/banks', (string) $httpRequest->getUri());

        $this->assertNull($response->getCode());
        $this->assertTrue($response->isSuccessful());
        $this->assertCount(6, $response->getBanks());
    }

    public function testListsPayoutBankCodes(): void
    {
        $codes = $this->fetchBanks()->getBankCodes();

        $this->assertSame('855', $codes['telebirr']);
        $this->assertSame('128', $codes['CBEBirr']);
        $this->assertSame('836', $codes['Cooperative Bank of Oromia (COOP)']);
    }

    public function testExposesExpectedAccountLength(): void
    {
        $response = $this->fetchBanks();

        $this->assertSame(10, $response->getAccountLength('855'));
        $this->assertSame(16, $response->getAccountLength('534'));
        $this->assertNull($response->getAccountLength('999999'));
    }

    /**
     * Every bank in the captured test-mode list can take payouts, so the filter
     * is exercised with a hand-built list shaped like the real records.
     */
    public function testBanksThatCannotTakePayoutsAreLeftOutOfBankCodes(): void
    {
        $request = new FetchBanksRequest($this->getHttpClient(), $this->getHttpRequest());
        $response = new FetchBanksResponse($request, [
            'message' => 'Banks retrieved',
            'data' => [
                ['id' => 855, 'name' => 'telebirr', 'can_process_payouts' => 1],
                ['id' => 700, 'name' => 'Payments Only Bank', 'can_process_payouts' => 0],
                ['id' => 701, 'name' => 'No Flag Bank'],
            ],
        ]);

        $this->assertSame(['telebirr' => '855', 'No Flag Bank' => '701'], $response->getBankCodes());
    }

    public function testErrorEnvelopeWithoutDataIsNotSuccessful(): void
    {
        $request = new FetchBanksRequest($this->getHttpClient(), $this->getHttpRequest());
        $response = new FetchBanksResponse($request, ['status' => 'failed', 'message' => 'Invalid API Key']);

        $this->assertFalse($response->isSuccessful());
        $this->assertSame([], $response->getBanks());
    }
}
