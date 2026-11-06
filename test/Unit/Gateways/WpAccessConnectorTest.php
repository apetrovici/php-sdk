<?php

namespace GlobalPayments\Api\Tests\Unit\Gateways;

use GlobalPayments\Api\Builders\AccessHppCreateTransactionBuilder;
use GlobalPayments\Api\Builders\AuthorizationBuilder;
use GlobalPayments\Api\Builders\ManagementBuilder;
use GlobalPayments\Api\Builders\RequestBuilder\RequestBuilderFactory;
use GlobalPayments\Api\Builders\RequestBuilder\WpAccess\AccessHppCreateTransactionRequestBuilder;
use GlobalPayments\Api\Builders\TransactionReportBuilder;
use GlobalPayments\Api\Entities\AccessWorldpayRequest;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\Api\Entities\Enums\PaymentMethodType;
use GlobalPayments\Api\Entities\Enums\ReportType;
use GlobalPayments\Api\Entities\Enums\TransactionType;
use GlobalPayments\Api\Entities\Exceptions\BuilderException;
use GlobalPayments\Api\Entities\Exceptions\UnsupportedTransactionException;
use GlobalPayments\Api\Gateways\IAccessCapabilities;
use GlobalPayments\Api\Gateways\IAccessRequestGateway;
use GlobalPayments\Api\Gateways\IPaymentGateway;
use GlobalPayments\Api\Gateways\WpAccessConnector;
use GlobalPayments\Api\PaymentMethods\TransactionReference;
use GlobalPayments\Api\ServiceConfigs\Gateways\WpAccessConfig;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for WpAccessConnector. Covers the still-unsupported `IPaymentGateway`
 * surface (carried over from the approved shell) plus the new
 * `processBuilderRequest()` HPP create-transaction dispatch.
 *
 * No network call is made anywhere in this test class: the no-builder-match
 * rejection path is exercised directly, and the request-assembly/header-merge
 * behavior that `processBuilderRequest()` would perform before transport is
 * verified by invoking the same `RequestBuilderFactory` lookup and
 * `buildRequest()` call it uses internally, stopping short of `doTransaction()`.
 */
class WpAccessConnectorTest extends TestCase
{
    private WpAccessConnector $connector;
    private WpAccessConfig $config;

    protected function setUp(): void
    {
        $this->config = new WpAccessConfig();
        $this->config->username = 'synthetic-user';
        $this->config->password = 'synthetic-pass';

        $this->connector = new WpAccessConnector($this->config);
    }

    public function testImplementsRequiredInterfaces(): void
    {
        $this->assertInstanceOf(IPaymentGateway::class, $this->connector);
        $this->assertInstanceOf(IAccessCapabilities::class, $this->connector);
        $this->assertInstanceOf(IAccessRequestGateway::class, $this->connector);
    }

    public function testConstructorSetsStableAuthenticationAndAcceptHeaders(): void
    {
        $reflection = new ReflectionClass($this->connector);
        $headersProperty = $reflection->getProperty('headers');
        $headersProperty->setAccessible(true);
        $headers = $headersProperty->getValue($this->connector);

        $this->assertSame(
            'Basic ' . base64_encode('synthetic-user:synthetic-pass'),
            $headers['Authorization']
        );
        $this->assertSame('application/vnd.worldpay.payment_pages-v1.hal+json', $headers['Accept']);

        $contentTypeProperty = $reflection->getProperty('contentType');
        $contentTypeProperty->setAccessible(true);
        $this->assertSame(
            'application/vnd.worldpay.payment_pages-v1.hal+json',
            $contentTypeProperty->getValue($this->connector)
        );
    }

    public function testSupportsOpenBankingIsFalse(): void
    {
        $this->assertFalse($this->connector->supportsOpenBanking());
    }

    public function testSupportsTransactionTypeIsFalseForEveryKnownTransactionType(): void
    {
        foreach ($this->allTransactionTypes() as $transactionType) {
            $this->assertFalse(
                $this->connector->supportsTransactionType($transactionType),
                sprintf('Expected transaction type %d to be unsupported.', $transactionType)
            );
        }
    }

    public function testSupportsPaymentMethodIsFalseForNullAndEveryKnownPaymentMethodType(): void
    {
        $this->assertFalse($this->connector->supportsPaymentMethod(null));

        foreach ($this->allPaymentMethodTypes() as $paymentMethodType) {
            $this->assertFalse(
                $this->connector->supportsPaymentMethod($paymentMethodType),
                sprintf('Expected payment method type %d to be unsupported.', $paymentMethodType)
            );
        }
    }

    public function testProcessAuthorizationThrowsUnsupportedTransactionException(): void
    {
        $builder = new AuthorizationBuilder(TransactionType::SALE);

        $this->expectException(UnsupportedTransactionException::class);
        $this->expectExceptionMessage('WP Access does not support authorization requests');

        $this->connector->processAuthorization($builder);
    }

    /**
     * @dataProvider managementTransactionTypeProvider
     */
    public function testManageTransactionThrowsUnsupportedTransactionExceptionForEveryTransactionType(
        int $transactionType
    ): void {
        $builder = new ManagementBuilder($transactionType, new TransactionReference());

        $this->expectException(UnsupportedTransactionException::class);
        $this->expectExceptionMessage('WP Access does not support management transaction type');

        $this->connector->manageTransaction($builder);
    }

    public static function managementTransactionTypeProvider(): array
    {
        return [
            'capture' => [TransactionType::CAPTURE],
            'refund' => [TransactionType::REFUND],
            'void' => [TransactionType::VOID],
        ];
    }

    public function testManageTransactionRejectsBeforeFactoryLookupForUnsupportedBuilder(): void
    {
        // VOID has no generic validation rule registered on ManagementBuilder,
        // so this exercises only the connector's own capability gate.
        $paymentMethod = new TransactionReference();
        $paymentMethod->transactionId = 'synthetic-shell-fixture';
        $builder = new ManagementBuilder(TransactionType::VOID, $paymentMethod);

        try {
            $this->connector->manageTransaction($builder);
            $this->fail('Expected an UnsupportedTransactionException to be thrown.');
        } catch (UnsupportedTransactionException $exception) {
            $this->assertStringContainsString(
                'WP Access does not support management transaction type',
                $exception->getMessage()
            );
        }
    }

    public function testGenericBuilderValidationFailsBeforeConnectorIsReached(): void
    {
        // CAPTURE requires a transactionId on ManagementBuilder itself; this
        // must fail during the builder's own validation, independent of any
        // connector/gateway configuration.
        $builder = new ManagementBuilder(TransactionType::CAPTURE, new TransactionReference());

        $this->expectException(BuilderException::class);

        $builder->execute();
    }

    public function testProcessReportThrowsUnsupportedTransactionException(): void
    {
        $builder = new TransactionReportBuilder(ReportType::TRANSACTION_DETAIL);

        $this->expectException(UnsupportedTransactionException::class);
        $this->expectExceptionMessage('WP Access does not support reporting requests');

        $this->connector->processReport($builder);
    }

    public function testSerializeRequestThrowsUnsupportedTransactionException(): void
    {
        $builder = new AuthorizationBuilder(TransactionType::SALE);

        $this->expectException(UnsupportedTransactionException::class);
        $this->expectExceptionMessage('WP Access hosted-payment serialization is not implemented');

        $this->connector->serializeRequest($builder);
    }

    public function testExecuteRequestThrowsUnsupportedTransactionException(): void
    {
        $request = new AccessWorldpayRequest('POST', '/synthetic-shell-fixture', '{}');

        $this->expectException(UnsupportedTransactionException::class);
        $this->expectExceptionMessage('Direct AccessWorldpayRequest execution is not implemented');

        $this->connector->executeRequest($request);
    }

    public function testProcessBuilderRequestThrowsUnsupportedTransactionExceptionWhenNoBuilderMatches(): void
    {
        // ManagementBuilder has no registered WP_ACCESS request builder, so
        // this must fail before any transport attempt.
        $builder = new ManagementBuilder(TransactionType::CAPTURE, new TransactionReference());

        $this->expectException(UnsupportedTransactionException::class);
        $this->expectExceptionMessage('no request builder is registered');

        $this->connector->processBuilderRequest($builder);
    }

    public function testRegisteredBuilderProducesExpectedRequestBeforeTransport(): void
    {
        // Exercises the same factory-lookup and buildRequest() steps that
        // processBuilderRequest() performs internally, without reaching
        // doTransaction(), since no network access is available in this
        // test environment.
        $builder = (new AccessHppCreateTransactionBuilder())
            ->withMerchantEntity('synthetic-merchant')
            ->withNarrativeLine1('synthetic narrative')
            ->withTransactionReference('synthetic-ref-001')
            ->withAmount(250)
            ->withCurrency('GBP')
            ->withCorrelationId('synthetic-correlation-id');

        $factory = new RequestBuilderFactory();
        $requestBuilder = $factory->getRequestBuilder($builder, GatewayProvider::WP_ACCESS);

        $this->assertInstanceOf(AccessHppCreateTransactionRequestBuilder::class, $requestBuilder);

        $request = $requestBuilder->buildRequest($builder, $this->config);

        $this->assertSame('POST', $request->httpVerb);
        $this->assertSame('/payment_pages', $request->endpoint);
        $this->assertSame('synthetic-correlation-id', $request->headers['WP-CorrelationId']);

        $decodedBody = json_decode($request->requestBody, true);
        $this->assertSame('synthetic-merchant', $decodedBody['merchant']['entity']);
        $this->assertSame('synthetic narrative', $decodedBody['narrative']['line1']);
        $this->assertSame('synthetic-ref-001', $decodedBody['transactionReference']);
        $this->assertSame(250, $decodedBody['value']['amount']);
        $this->assertSame('GBP', $decodedBody['value']['currency']);
    }

    /** @return int[] */
    private function allTransactionTypes(): array
    {
        return array_values((new ReflectionClass(TransactionType::class))->getConstants());
    }

    /** @return int[] */
    private function allPaymentMethodTypes(): array
    {
        return array_values((new ReflectionClass(PaymentMethodType::class))->getConstants());
    }
}

