<?php

namespace GlobalPayments\Api\Tests\Unit\Entities\WpAccess;

use GlobalPayments\Api\Entities\WpAccess\ResultUrls;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ResultUrlsTest extends TestCase
{
    public function testValidateAcceptsOptionalAndValidEndpoints(): void
    {
        $urls = new ResultUrls();
        $this->assertSame([], $urls->validate());

        foreach ([
            'successUrl',
            'pendingUrl',
            'failureUrl',
            'errorUrl',
            'cancelUrl',
            'expiryUrl',
        ] as $property) {
            $urls->$property = 'https://example.test/' . $property;
            $this->assertSame([], $urls->validate());
        }
    }

    /**
     * @dataProvider invalidUrlProvider
     */
    #[DataProvider('invalidUrlProvider')]
    public function testValidateReportsInvalidEndpointWithoutMutatingValues(
        string $property,
        string $field,
        string $value
    ): void {
        $urls = new ResultUrls();
        $urls->$property = $value;
        $before = get_object_vars($urls);

        $this->assertSame(
            ['Invalid URL format for resultURLs.' . $field],
            $urls->validate()
        );
        $this->assertSame($before, get_object_vars($urls));
    }

    public static function invalidUrlProvider(): array
    {
        $cases = [];
        foreach ([
            'successUrl' => 'successURL',
            'pendingUrl' => 'pendingURL',
            'failureUrl' => 'failureURL',
            'errorUrl' => 'errorURL',
            'cancelUrl' => 'cancelURL',
            'expiryUrl' => 'expiryURL',
        ] as $property => $field) {
            $cases[$field . ' empty'] = [$property, $field, ''];
            $cases[$field . ' malformed'] = [$property, $field, 'not-a-url'];
        }

        return $cases;
    }

    public function testValidateReturnsAllErrorsInEndpointOrder(): void
    {
        $urls = new ResultUrls();
        $urls->expiryUrl = '';
        $urls->successUrl = 'not-a-url';
        $urls->errorUrl = 'invalid';
        $before = get_object_vars($urls);

        $this->assertSame([
            'Invalid URL format for resultURLs.successURL',
            'Invalid URL format for resultURLs.errorURL',
            'Invalid URL format for resultURLs.expiryURL',
        ], $urls->validate());
        $this->assertSame($before, get_object_vars($urls));
    }
}
