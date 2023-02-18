<?php

namespace Vahidkaargar\test;

use PHPUnit\Framework\TestCase;
use Vahidkaargar\Jibit\Jibit;

class JibitTest extends TestCase
{
    private $jibit;

    protected function setUp(): void
    {
        $apiKey = 'my-test-api-key';
        $secretKey = 'my-test-secret-key';
        $this->jibit = new Jibit($apiKey, $secretKey);
    }

    public function testPaymentRequest(): void
    {
        $amount = 5000;
        $referenceNumber = 'ABC1234';
        $userIdentifier = 'user-1234';
        $callbackUrl = 'https://example.com/callback';
        $currency = 'IRR';
        $description = 'Test payment';
        $additionalData = ['foo' => 'bar'];

        $result = $this->jibit->paymentRequest($amount, $referenceNumber, $userIdentifier, $callbackUrl, $currency, $description, $additionalData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('code', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertSame('success', $result['code']);
    }

    public function testGetOrderById(): void
    {
        $result = $this->jibit->getOrderById(1234);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('code', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertSame('success', $result['code']);
    }
}