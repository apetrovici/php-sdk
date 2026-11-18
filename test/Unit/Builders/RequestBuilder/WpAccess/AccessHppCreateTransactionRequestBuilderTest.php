<?php

namespace GlobalPayments\Api\Tests\Unit\Builders\RequestBuilder\WpAccess;

use GlobalPayments\Api\Builders\AccessHppCreateTransactionBuilder;
use GlobalPayments\Api\Builders\ManagementBuilder;
use GlobalPayments\Api\Builders\RequestBuilder\WpAccess\AccessHppCreateTransactionRequestBuilder;
use GlobalPayments\Api\Entities\WpAccessRequest;
use GlobalPayments\Api\Entities\Enums\TransactionType;
use GlobalPayments\Api\Entities\Exceptions\ArgumentException;
use GlobalPayments\Api\Entities\Exceptions\UnsupportedTransactionException;
use GlobalPayments\Api\Entities\WpAccess\ResultUrls;
use GlobalPayments\Api\PaymentMethods\TransactionReference;
use GlobalPayments\Api\ServiceConfigs\Gateways\WpAccessConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AccessHppCreateTransactionRequestBuilder. No network call is
 * made; only request assembly is verified.
 */
class AccessHppCreateTransactionRequestBuilderTest extends TestCase
{
    private AccessHppCreateTransactionRequestBuilder $requestBuilder;
    private WpAccessConfig $config;

    protected function setUp(): void
    {
        $this->requestBuilder = new AccessHppCreateTransactionRequestBuilder();
        $this->config = new WpAccessConfig();
        $this->config->username = 'synthetic-user';
        $this->config->password = 'synthetic-pass';
    }

    public function testCanProcessReturnsTrueOnlyForMatchingBuilder(): void
    {
        $this->assertTrue(
            AccessHppCreateTransactionRequestBuilder::canProcess(new AccessHppCreateTransactionBuilder())
        );
        $this->assertFalse(
            AccessHppCreateTransactionRequestBuilder::canProcess(
                new ManagementBuilder(TransactionType::CAPTURE, new TransactionReference())
            )
        );
        $this->assertFalse(AccessHppCreateTransactionRequestBuilder::canProcess(null));
    }

    public function testBuildRequestProducesExpectedWpAccessRequest(): void
    {
        $builder = (new AccessHppCreateTransactionBuilder())
            ->withMerchantEntity('synthetic-merchant')
            ->withNarrativeLine1('synthetic narrative')
            ->withTransactionReference('synthetic-ref-001')
            ->withAmount(999)
            ->withCurrency('EUR')
            ->withCorrelationId('fixed-correlation-id')
            ->withUserAgent('synthetic-sdk/1.0');

        $request = $this->requestBuilder->buildRequest($builder, $this->config);

        $this->assertInstanceOf(WpAccessRequest::class, $request);
        $this->assertSame('POST', $request->httpVerb);
        $this->assertSame('/payment_pages', $request->endpoint);
        $this->assertSame('fixed-correlation-id', $request->headers['WP-CorrelationId']);
        $this->assertSame('synthetic-sdk/1.0', $request->headers['User-Agent']);

        $body = json_decode($request->requestBody, true);
        $this->assertSame('synthetic-merchant', $body['merchant']['entity']);
        $this->assertSame('synthetic narrative', $body['narrative']['line1']);
        $this->assertSame('synthetic-ref-001', $body['transactionReference']);
        $this->assertSame(999, $body['value']['amount']);
        $this->assertSame('EUR', $body['value']['currency']);
        $this->assertArrayNotHasKey('description', $body);
        $this->assertArrayNotHasKey('resultURLs', $body);
    }

    public function testBuildRequestOmitsUserAgentHeaderWhenNotSupplied(): void
    {
        $builder = (new AccessHppCreateTransactionBuilder())
            ->withMerchantEntity('synthetic-merchant')
            ->withNarrativeLine1('synthetic narrative')
            ->withTransactionReference('synthetic-ref-001')
            ->withAmount(100)
            ->withCurrency('USD')
            ->withCorrelationId('fixed-correlation-id');

        $request = $this->requestBuilder->buildRequest($builder, $this->config);

        $this->assertArrayNotHasKey('User-Agent', $request->headers);
    }

    public function testBuildRequestMergesAdditionalFieldsIntoBody(): void
    {
        $builder = (new AccessHppCreateTransactionBuilder())
            ->withMerchantEntity('synthetic-merchant')
            ->withNarrativeLine1('synthetic narrative')
            ->withTransactionReference('synthetic-ref-001')
            ->withAmount(100)
            ->withCurrency('USD')
            ->withCorrelationId('fixed-correlation-id')
            ->withAdditionalField('resultURLs', ['successURL' => 'https://example.test/success']);

        $request = $this->requestBuilder->buildRequest($builder, $this->config);

        $body = json_decode($request->requestBody, true);
        $this->assertSame('https://example.test/success', $body['resultURLs']['successURL']);
    }

    /**
     * @dataProvider resultUrlsProvider
     */
    #[DataProvider('resultUrlsProvider')]
    public function testBuildRequestSerializesDescriptionAndOptionalResultUrls(
        array $properties,
        array $expectedUrls
    ): void {
        $urls = new ResultUrls();
        foreach ($properties as $property => $url) {
            $urls->$property = $url;
        }

        $builder = (new AccessHppCreateTransactionBuilder())
            ->withMerchantEntity('synthetic-merchant')
            ->withNarrativeLine1('synthetic narrative')
            ->withTransactionReference('synthetic-ref-001')
            ->withAmount(100)
            ->withCurrency('USD')
            ->withDescription('Order 123')
            ->withAdditionalField('resultURLs', ['cancelURL' => 'https://example.test/previous'])
            ->withResultUrls($urls);

        $request = $this->requestBuilder->buildRequest($builder, $this->config);
        $body = json_decode($request->requestBody, true);

        $expected = [
            'description' => 'Order 123',
            'merchant' => ['entity' => 'synthetic-merchant'],
            'narrative' => ['line1' => 'synthetic narrative'],
            'transactionReference' => 'synthetic-ref-001',
            'value' => ['amount' => 100, 'currency' => 'USD'],
        ];
        if ($expectedUrls !== []) {
            $expected['resultURLs'] = $expectedUrls;
        }

        $this->assertEquals($expected, $body);
        $this->assertSame(100, $body['value']['amount']);
        if ($expectedUrls !== []) {
            $this->assertSame($expectedUrls, $body['resultURLs']);
        } else {
            $this->assertArrayNotHasKey('resultURLs', $body);
        }
    }

    public static function resultUrlsProvider(): array
    {
        return [
            'all endpoints' => [
                [
                    'successUrl' => 'https://example.test/success?order=123&source=hpp',
                    'pendingUrl' => 'https://example.test/pending',
                    'failureUrl' => 'https://example.test/failure',
                    'errorUrl' => 'https://example.test/error',
                    'cancelUrl' => 'https://example.test/cancel',
                    'expiryUrl' => 'https://example.test/expiry',
                ],
                [
                    'successURL' => 'https://example.test/success?order=123&source=hpp',
                    'pendingURL' => 'https://example.test/pending',
                    'failureURL' => 'https://example.test/failure',
                    'errorURL' => 'https://example.test/error',
                    'cancelURL' => 'https://example.test/cancel',
                    'expiryURL' => 'https://example.test/expiry',
                ],
            ],
            'partial endpoints' => [
                ['successUrl' => 'https://example.test/success'],
                ['successURL' => 'https://example.test/success'],
            ],
            'no endpoints' => [[], []],
        ];
    }

    public function testBuildRequestRejectsNonMatchingBuilderType(): void
    {
        $builder = new ManagementBuilder(TransactionType::CAPTURE, new TransactionReference());

        $this->expectException(ArgumentException::class);

        $this->requestBuilder->buildRequest($builder, $this->config);
    }

    public function testBuildRequestRejectsNonWpAccessConfig(): void
    {
        $builder = (new AccessHppCreateTransactionBuilder())
            ->withMerchantEntity('synthetic-merchant')
            ->withNarrativeLine1('synthetic narrative')
            ->withTransactionReference('synthetic-ref-001')
            ->withAmount(100)
            ->withCurrency('USD')
            ->withCorrelationId('fixed-correlation-id');

        $this->expectException(ArgumentException::class);

        $this->requestBuilder->buildRequest($builder, new \stdClass());
    }

    public function testBuildRequestFromJsonIsNotSupportedInThisVersion(): void
    {
        $this->expectException(UnsupportedTransactionException::class);

        $this->requestBuilder->buildRequestFromJson('{}', $this->config);
    }
}
