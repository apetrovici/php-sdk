<?php

namespace GlobalPayments\Api\Tests\Unit\Builders\RequestBuilder\WpAccess;

use GlobalPayments\Api\Builders\AccessHppCreateTransactionBuilder;
use GlobalPayments\Api\Builders\ManagementBuilder;
use GlobalPayments\Api\Builders\RequestBuilder\WpAccess\AccessHppCreateTransactionRequestBuilder;
use GlobalPayments\Api\Entities\AccessWorldpayRequest;
use GlobalPayments\Api\Entities\Enums\TransactionType;
use GlobalPayments\Api\Entities\Exceptions\ArgumentException;
use GlobalPayments\Api\Entities\Exceptions\UnsupportedTransactionException;
use GlobalPayments\Api\PaymentMethods\TransactionReference;
use GlobalPayments\Api\ServiceConfigs\Gateways\WpAccessConfig;
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

    public function testBuildRequestProducesExpectedAccessWorldpayRequest(): void
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

        $this->assertInstanceOf(AccessWorldpayRequest::class, $request);
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

