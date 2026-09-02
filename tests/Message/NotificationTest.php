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

    public function testFallsBackToTxRefWhenNoReference(): void
    {
        $body = '{"event":"charge.success","status":"success","tx_ref":"order-1234"}';

        $notification = new Notification($this->request($body, $this->sign($body)), self::SECRET);

        $this->assertSame('order-1234', $notification->getTransactionReference());
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
