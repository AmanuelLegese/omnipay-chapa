<?php

namespace Omnipay\Chapa\Tests\Message;

use Omnipay\Chapa\Message\Notification;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\NotificationInterface;
use Omnipay\Tests\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

class NotificationTest extends TestCase
{
    private const SECRET = 'CHASECK_TEST-abc123';

    private const PAYLOAD = '{"event":"charge.success","status":"success",'
        . '"tx_ref":"order-1234","reference":"6jnheVKQEmy","amount":100}';

    private function request(string $body, ?string $signature): HttpRequest
    {
        $headers = $signature === null ? [] : ['HTTP_X_CHAPA_SIGNATURE' => $signature];

        return new HttpRequest([], [], [], [], [], $headers, $body);
    }

    private function sign(string $body, string $secret = self::SECRET): string
    {
        return hash_hmac('sha256', $body, $secret);
    }

    public function testAcceptsCorrectlySignedEvent(): void
    {
        $notification = new Notification(
            $this->request(self::PAYLOAD, $this->sign(self::PAYLOAD)),
            self::SECRET
        );

        $this->assertSame(NotificationInterface::STATUS_COMPLETED, $notification->getTransactionStatus());
        $this->assertSame('6jnheVKQEmy', $notification->getTransactionReference());
        $this->assertSame('charge.success', $notification->getMessage());
        $this->assertSame('order-1234', $notification->getData()['tx_ref']);
    }

    /**
     * The merchant's id and Chapa's id are separate accessors, so a charge
     * without a Chapa reference yet does not silently substitute the tx_ref.
     */
    public function testChargeSeparatesMerchantIdFromChapaReference(): void
    {
        $body = '{"event":"charge.success","status":"success","tx_ref":"order-1234"}';

        $notification = new Notification($this->request($body, $this->sign($body)), self::SECRET);

        $this->assertSame('order-1234', $notification->getTransactionId());
        $this->assertNull($notification->getTransactionReference());
    }

    /**
     * The trap this guards: Chapa sends payout events to the same URL with the
     * same signature, and a payout.success is "completed" too. A handler that
     * settles an order on any completed event would mark an order paid when a
     * payout went out. isCharge()/isPayout() make the difference explicit, and
     * the payout's own reference never surfaces as a payment reference.
     *
     * Payload from Chapa's webhook documentation.
     */
    public function testPayoutEventIsDistinguishableFromAPayment(): void
    {
        $body = '{"event":"payout.success","type":"Payout","account_name":"Customer Name",'
            . '"account_number":"25190000000","bank_id":855,"bank_name":"telebirr","amount":"2000.00",'
            . '"charge":"60.00","currency":"ETB","status":"success","reference":"MYMER3434989",'
            . '"chapa_reference":"2o10dfs332U","bank_reference":"GT3412w3"}';

        $notification = new Notification($this->request($body, $this->sign($body)), self::SECRET);

        $this->assertTrue($notification->isPayout());
        $this->assertFalse($notification->isCharge());
        $this->assertSame('payout.success', $notification->getEvent());
        // Still completed - which is why isCharge() has to be checked first.
        $this->assertSame(NotificationInterface::STATUS_COMPLETED, $notification->getTransactionStatus());
        $this->assertSame('MYMER3434989', $notification->getTransactionId());
        $this->assertSame('2o10dfs332U', $notification->getTransactionReference());
    }

    /**
     * Payload from Chapa's webhook documentation.
     */
    public function testChargeEventCarriesPaymentDetails(): void
    {
        $body = '{"event":"charge.success","first_name":"","last_name":"","email":null,'
            . '"mobile":"25190000000","currency":"ETB","amount":"400.00","charge":"12.00","status":"success",'
            . '"mode":"live","reference":"AP634JFwEbxd","type":"API","tx_ref":"4FGFF4FFGD3",'
            . '"payment_method":"telebirr","customization":{"title":null},"meta":null}';

        $notification = new Notification($this->request($body, $this->sign($body)), self::SECRET);

        $this->assertTrue($notification->isCharge());
        $this->assertFalse($notification->isPayout());
        $this->assertSame('4FGFF4FFGD3', $notification->getTransactionId());
        $this->assertSame('AP634JFwEbxd', $notification->getTransactionReference());
        // Webhooks say payment_method where the verify endpoint says method.
        $this->assertSame('telebirr', $notification->getPaymentMethod());
        $this->assertSame('400.00', $notification->getAmount());
        $this->assertSame('charge.success', $notification->getMessage());
    }

    /**
     * NotificationInterface only has completed/pending/failed, so a refund has
     * to report failed there - but the money was taken first, and isRefunded()
     * must say so.
     */
    public function testRefundedChargeIsNotMistakenForAFailedPayment(): void
    {
        $body = '{"event":"charge.refunded","status":"refunded","tx_ref":"order-1234","reference":"AP634JFwEbxd"}';

        $notification = new Notification($this->request($body, $this->sign($body)), self::SECRET);

        $this->assertTrue($notification->isCharge());
        $this->assertTrue($notification->isRefunded());
        $this->assertSame(NotificationInterface::STATUS_FAILED, $notification->getTransactionStatus());
    }

    public function testFailedChargeExposesChapaFailureReason(): void
    {
        $body = '{"event":"charge.failed","status":"failed/cancelled","tx_ref":"order-1234",'
            . '"failure_reason":"USSD Push service, the user has not entered the PIN for an extended period."}';

        $notification = new Notification($this->request($body, $this->sign($body)), self::SECRET);

        $this->assertSame(NotificationInterface::STATUS_FAILED, $notification->getTransactionStatus());
        $this->assertSame('failed/cancelled', $notification->getStatus());
        $this->assertStringStartsWith('USSD Push service', (string) $notification->getMessage());
    }

    public function testMapsPendingStatus(): void
    {
        $body = '{"status":"pending","tx_ref":"order-1234"}';

        $notification = new Notification($this->request($body, $this->sign($body)), self::SECRET);

        $this->assertSame(NotificationInterface::STATUS_PENDING, $notification->getTransactionStatus());
    }

    /**
     * An unrecognised status must never read as a completed payment, so that a
     * status Chapa adds later fails safe.
     */
    public function testUnknownStatusIsTreatedAsFailed(): void
    {
        $body = '{"status":"something-new","tx_ref":"order-1234"}';

        $notification = new Notification($this->request($body, $this->sign($body)), self::SECRET);

        $this->assertSame(NotificationInterface::STATUS_FAILED, $notification->getTransactionStatus());
    }

    /**
     * The core guarantee: a body altered after signing must not validate.
     */
    public function testRejectsTamperedPayload(): void
    {
        $signature = $this->sign(self::PAYLOAD);
        $forged = str_replace('"amount":100', '"amount":1', self::PAYLOAD);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Invalid Chapa webhook signature');

        new Notification($this->request($forged, $signature), self::SECRET);
    }

    public function testRejectsSignatureFromAnotherSecret(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Invalid Chapa webhook signature');

        new Notification(
            $this->request(self::PAYLOAD, $this->sign(self::PAYLOAD, 'CHASECK_TEST-wrong')),
            self::SECRET
        );
    }

    public function testRejectsMissingSignatureHeader(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Missing x-chapa-signature header');

        new Notification($this->request(self::PAYLOAD, null), self::SECRET);
    }

    /**
     * Chapa-Signature is a constant - an HMAC of the secret keyed by itself,
     * identical on every webhook. Accepting it would let anyone who has seen
     * one webhook forge any payload, so it must not validate on its own.
     */
    public function testConstantChapaSignatureHeaderIsNotAccepted(): void
    {
        $constant = hash_hmac('sha256', self::SECRET, self::SECRET);

        $request = new HttpRequest(
            [],
            [],
            [],
            [],
            [],
            ['HTTP_CHAPA_SIGNATURE' => $constant],
            self::PAYLOAD
        );

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Missing x-chapa-signature header');

        new Notification($request, self::SECRET);
    }

    public function testRequiresASecret(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('The webhookSecret parameter is required');

        new Notification($this->request(self::PAYLOAD, 'whatever'), null);
    }

    /**
     * Signing must use the bytes as they arrived; re-encoding the decoded JSON
     * changes key order and escaping, which would break valid signatures.
     */
    public function testVerifiesAgainstRawBodyNotReencodedJson(): void
    {
        $body = '{"status":"success","tx_ref":"order/1234","note":"café",'
            . '"amount":100.50,"nested":{"b":1,"a":2}}';

        $notification = new Notification($this->request($body, $this->sign($body)), self::SECRET);

        $this->assertSame(NotificationInterface::STATUS_COMPLETED, $notification->getTransactionStatus());
        $this->assertNotSame(
            $body,
            json_encode($notification->getData()),
            'Fixture must not round-trip, otherwise this test proves nothing'
        );
    }
}
