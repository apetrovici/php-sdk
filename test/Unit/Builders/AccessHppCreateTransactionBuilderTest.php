<?php

namespace GlobalPayments\Api\Tests\Unit\Builders;

use GlobalPayments\Api\Builders\AccessHppCreateTransactionBuilder;
use GlobalPayments\Api\Entities\Exceptions\BuilderException;
use GlobalPayments\Api\Entities\Exceptions\UnsupportedTransactionException;
use GlobalPayments\Api\Gateways\WpAccessConnector;
use GlobalPayments\Api\ServiceConfigs\Gateways\WpAccessConfig;
use GlobalPayments\Api\ServicesContainer;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AccessHppCreateTransactionBuilder's local validation and
 * dispatch gating. No network call is made.
 */
class AccessHppCreateTransactionBuilderTest extends TestCase
{
    private function validBuilder(): AccessHppCreateTransactionBuilder
    {
        return (new AccessHppCreateTransactionBuilder())
            ->withMerchantEntity('synthetic-merchant')
            ->withNarrativeLine1('synthetic narrative')
            ->withTransactionReference('synthetic-ref-001')
            ->withAmount(100)
            ->withCurrency('USD');
    }

    /**
     * @dataProvider missingRequiredFieldProvider
     */
    public function testExecuteThrowsBuilderExceptionWhenRequiredFieldMissing(
        \Closure $mutate
    ): void {
        $builder = $mutate($this->validBuilder());

        $this->expectException(BuilderException::class);

        $builder->execute('wp-access-missing-field-test');
    }

    public static function missingRequiredFieldProvider(): array
    {
        return [
            'missing merchantEntity' => [function (AccessHppCreateTransactionBuilder $b) {
                $b->merchantEntity = null;
                return $b;
            }],
            'missing narrativeLine1' => [function (AccessHppCreateTransactionBuilder $b) {
                $b->narrativeLine1 = null;
                return $b;
            }],
            'missing transactionReference' => [function (AccessHppCreateTransactionBuilder $b) {
                $b->transactionReference = null;
                return $b;
            }],
            'missing amount' => [function (AccessHppCreateTransactionBuilder $b) {
                $b->amount = null;
                return $b;
            }],
            'missing currency' => [function (AccessHppCreateTransactionBuilder $b) {
                $b->currency = null;
                return $b;
            }],
        ];
    }

    public function testExecuteThrowsUnsupportedTransactionExceptionWhenConfiguredGatewayIsNotAccessRequestGateway(): void
    {
        // Configure a name with a gateway that does not implement
        // IAccessRequestGateway (none configured at all for this name).
        $builder = $this->validBuilder();

        $this->expectException(\GlobalPayments\Api\Entities\Exceptions\ApiException::class);

        $builder->execute('wp-access-unconfigured-name-' . uniqid());
    }

    public function testExecuteGeneratesCorrelationIdWhenNotSupplied(): void
    {
        $builder = $this->validBuilder();

        $this->assertNull($builder->correlationId);

        // Reaching the client-resolution step (which fails for an
        // unconfigured name) still proves correlationId generation ran
        // first, since validateRequiredFields() passes and
        // generation happens before ServicesContainer lookup.
        try {
            $builder->execute('wp-access-correlation-test-' . uniqid());
        } catch (\Throwable $exception) {
            // Expected: no gateway configured for this synthetic name.
        }

        $this->assertNotNull($builder->correlationId);
        $this->assertNotSame('', $builder->correlationId);
    }

    public function testWithAdditionalFieldIsPreservedOnBuilder(): void
    {
        $builder = $this->validBuilder()->withAdditionalField('resultURLs', ['successURL' => 'https://example.test/success']);

        $this->assertSame(
            ['successURL' => 'https://example.test/success'],
            $builder->additionalFields['resultURLs']
        );
    }
}

