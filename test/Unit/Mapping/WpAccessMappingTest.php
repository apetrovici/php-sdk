<?php

namespace GlobalPayments\Api\Tests\Unit\Mapping;

use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\Mapping\WpAccessMapping;
use PHPUnit\Framework\TestCase;

/**
 * Tests for WpAccessMapping::mapCreateTransactionResponse(), mapping
 * the documented 200 response shape (`_links.self.href`, `url`) onto
 * HostedPaymentPageResponse. Response schema evidence:
 * `gp-php-sdk-fork/skills/wpaccessapi/SKILL.md:772-789`.
 */
class WpAccessMappingTest extends TestCase
{
    public function testMapsSelfLinkAndRedirectUrl(): void
    {
        $raw = json_encode([
            '_links' => ['self' => ['href' => 'https://access.worldpay.com/payment_pages/synthetic-ref-001']],
            'url' => 'https://try.access.worldpay.com/hpp/synthetic-token',
        ]);

        $response = WpAccessMapping::mapCreateTransactionResponse($raw);

        $this->assertSame(
            'https://access.worldpay.com/payment_pages/synthetic-ref-001',
            $response->selfLink
        );
        $this->assertSame('https://try.access.worldpay.com/hpp/synthetic-token', $response->redirectUrl);
    }

    public function testToleratesMissingFieldsByReturningNull(): void
    {
        $response = WpAccessMapping::mapCreateTransactionResponse('{}');

        $this->assertNull($response->selfLink);
        $this->assertNull($response->redirectUrl);
    }

    public function testThrowsApiExceptionForMalformedJson(): void
    {
        $this->expectException(ApiException::class);

        WpAccessMapping::mapCreateTransactionResponse('not-json');
    }
}
