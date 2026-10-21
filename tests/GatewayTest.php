<?php

namespace Omnipay\Chapa\Tests;

use Omnipay\Chapa\Gateway;
use Omnipay\Chapa\Message\BulkTransferRequest;
use Omnipay\Chapa\Message\CreateSubaccountRequest;
use Omnipay\Chapa\Message\FetchBanksRequest;
use Omnipay\Chapa\Message\FetchRefundRequest;
use Omnipay\Chapa\Message\FetchTransactionRequest;
use Omnipay\Chapa\Message\FetchTransferRequest;
use Omnipay\Chapa\Message\MobileInitializeRequest;
use Omnipay\Chapa\Message\RefundRequest;
use Omnipay\Chapa\Message\TransferRequest;
use Omnipay\Chapa\Message\VoidRequest;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Tests\GatewayTestCase;

class GatewayTest extends GatewayTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new Gateway($this->getHttpClient(), $this->getHttpRequest());
        $this->gateway->setSecretKey('CHASECK_TEST-abc123');
    }

    /**
     * Omnipay::create('Chapa') resolves Omnipay\Chapa\Gateway by naming
     * convention, so the short name has to stay 'Chapa'.
     */
    public function testResolvesByConventionalName(): void
    {
        $this->assertSame('Chapa', $this->gateway->getName());
        $this->assertSame('Chapa', $this->gateway->getShortName());
    }

    public function testSupportsTheStandardMethodsChapaOffers(): void
    {
        $this->assertTrue($this->gateway->supportsPurchase());
        $this->assertTrue($this->gateway->supportsCompletePurchase());
        $this->assertTrue($this->gateway->supportsAcceptNotification());
        $this->assertTrue($this->gateway->supportsFetchTransaction());
        $this->assertTrue($this->gateway->supportsRefund());
        $this->assertTrue($this->gateway->supportsVoid());

        // Chapa has no separate authorize/capture step.
        $this->assertFalse($this->gateway->supportsAuthorize());
        $this->assertFalse($this->gateway->supportsCapture());
    }

    public function testWebhookSecretFallsBackToSecretKey(): void
    {
        $this->assertSame('CHASECK_TEST-abc123', $this->gateway->getWebhookSecret());

        $this->gateway->setWebhookSecret('whsec-separate');
        $this->assertSame('whsec-separate', $this->gateway->getWebhookSecret());
    }

    /**
     * Payouts sit outside GatewayInterface, so they are only reachable on the
     * concrete gateway. Pin that they are wired up at all.
     */
    public function testExposesTransferMethods(): void
    {
        $this->assertInstanceOf(
            TransferRequest::class,
            $this->gateway->transfer(['amount' => '100.00'])
        );
        $this->assertInstanceOf(
            FetchTransferRequest::class,
            $this->gateway->fetchTransfer(['transactionId' => 'payout-0001'])
        );
        $this->assertInstanceOf(FetchBanksRequest::class, $this->gateway->fetchBanks());
    }

    public function testExposesRefundVoidAndFetchMethods(): void
    {
        $this->assertInstanceOf(
            FetchTransactionRequest::class,
            $this->gateway->fetchTransaction(['transactionId' => 'order-1234'])
        );
        $this->assertInstanceOf(
            RefundRequest::class,
            $this->gateway->refund(['transactionReference' => 'APjTAGhdvZTaI'])
        );
        $this->assertInstanceOf(
            FetchRefundRequest::class,
            $this->gateway->fetchRefund(['refundId' => 'TEST-DIS-REF-hiZb0ylpDzo'])
        );
        $this->assertInstanceOf(
            VoidRequest::class,
            $this->gateway->void(['transactionId' => 'order-1234'])
        );
    }

    public function testExposesSplitPaymentAndBulkMethods(): void
    {
        $this->assertInstanceOf(
            CreateSubaccountRequest::class,
            $this->gateway->createSubaccount(['businessName' => 'Example Vendor'])
        );
        $this->assertInstanceOf(
            BulkTransferRequest::class,
            $this->gateway->bulkTransfer(['title' => 'January payouts'])
        );
        $this->assertInstanceOf(
            MobileInitializeRequest::class,
            $this->gateway->mobileInitialize(['amount' => '100.00'])
        );
    }

    /**
     * Mobile initialize shares the purchase payload and differs only in the
     * endpoint it posts to.
     */
    public function testMobileInitializeUsesItsOwnEndpoint(): void
    {
        $this->setMockHttpResponse('PurchaseSuccess.txt');

        $this->gateway->mobileInitialize([
            'amount' => '100.00',
            'currency' => 'ETB',
            'transactionId' => 'order-1234',
        ])->send();

        $this->assertSame(
            'https://api.chapa.co/v1/transaction/mobile-initialize',
            (string) $this->getMockClient()->getLastRequest()->getUri()
        );
    }

    public function testAcceptNotificationRejectsUnsignedWebhook(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Missing x-chapa-signature header');

        $this->gateway->acceptNotification();
    }

    public function testPurchaseRedirectsToHostedCheckout(): void
    {
        $this->setMockHttpResponse('PurchaseSuccess.txt');

        $response = $this->gateway->purchase([
            'amount' => '100.00',
            'currency' => 'ETB',
            'transactionId' => 'order-1234',
        ])->send();

        // A redirect is never a completed payment.
        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isRedirect());
        $this->assertSame(
            'https://checkout.chapa.co/checkout/payment/abc123',
            $response->getRedirectUrl()
        );
    }

    public function testCompletePurchaseVerifiesTransaction(): void
    {
        $this->setMockHttpResponse('CompletePurchaseSuccess.txt');

        $response = $this->gateway->completePurchase([
            'transactionId' => 'omnipay-chapa-probe-tx4-1789327377',
        ])->send();

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('APjTAGhdvZTaI', $response->getTransactionReference());
    }
}
