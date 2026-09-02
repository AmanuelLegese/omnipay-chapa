<?php

namespace Omnipay\Chapa\Tests\Message;

use Omnipay\Chapa\Message\CreateSubaccountRequest;
use Omnipay\Chapa\Message\CreateSubaccountResponse;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Tests\TestCase;

class CreateSubaccountRequestTest extends TestCase
{
    private function subaccount(): CreateSubaccountRequest
    {
        $request = new CreateSubaccountRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize([
            'secretKey' => 'CHASECK_TEST-abc123',
            'businessName' => 'Example Vendor',
            'accountName' => 'Israel Goytom',
            'accountNumber' => '1000212659000',
            'bankCode' => '946',
            'splitType' => CreateSubaccountRequest::SPLIT_TYPE_PERCENTAGE,
            'splitValue' => 0.03,
        ]);

        return $request;
    }

    public function testBuildsSubaccountPayload(): void
    {
        $this->assertSame([
            'business_name' => 'Example Vendor',
            'account_name' => 'Israel Goytom',
            'bank_code' => '946',
            'account_number' => '1000212659000',
            'split_type' => 'percentage',
            'split_value' => 0.03,
        ], $this->subaccount()->getData());
    }

    public function testRequiresSplitType(): void
    {
        $request = $this->subaccount();
        $request->setSplitType(null);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('The splitType parameter is required');

        $request->getData();
    }

    /**
     * Chapa's docs and their nestjs SDK say /subaccount; their own Laravel
     * package posts to /sub-accounts. We follow the two-source majority, and
     * this pins which one we actually send.
     */
    public function testPostsToTheSingularSubaccountEndpointByDefault(): void
    {
        $request = $this->subaccount();
        $this->setMockHttpResponse('CreateSubaccountSuccess.txt');

        $request->send();

        $this->assertSame(
            'https://api.chapa.co/v1/subaccount',
            (string) $this->getMockClient()->getLastRequest()->getUri()
        );
    }

    /**
     * Because that choice may be wrong, overriding it must not require a fork.
     */
    public function testEndpointPathCanBeOverridden(): void
    {
        $request = $this->subaccount();
        $this->setMockHttpResponse('CreateSubaccountSuccess.txt');
        $request->setEndpointPath('/sub-accounts');

        $request->send();

        $this->assertSame(
            'https://api.chapa.co/v1/sub-accounts',
            (string) $this->getMockClient()->getLastRequest()->getUri()
        );
    }

    public function testReturnsTheNewSubaccountId(): void
    {
        $request = $this->subaccount();
        $this->setMockHttpResponse('CreateSubaccountSuccess.txt');

        /** @var CreateSubaccountResponse $response */
        $response = $request->send();

        $this->assertTrue($response->isSuccessful());
        // Chapa sends the id as a bare string in `data`, not an object.
        $this->assertSame('ac2e6b5b-0e76-464a-8c20-2d441fbaca6c', $response->getSubaccountId());
    }

    public function testReportsRejectedSubaccount(): void
    {
        $request = $this->subaccount();
        $this->setMockHttpResponse('CreateSubaccountFailure.txt');

        /** @var CreateSubaccountResponse $response */
        $response = $request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertNull($response->getSubaccountId());
        $this->assertSame('account_number: The account number is invalid.', $response->getMessage());
    }
}
