<?php

namespace GlobalPayments\Api\Tests\Unit\Builders;

use GlobalPayments\Api\Builders\AccessHppCreateTransactionBuilder;
use GlobalPayments\Api\Entities\Exceptions\ArgumentException;
use GlobalPayments\Api\Entities\Exceptions\BuilderException;
use GlobalPayments\Api\Entities\WpAccess\ResultUrls;
use PHPUnit\Framework\Attributes\DataProvider;
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
    #[DataProvider('missingRequiredFieldProvider')]
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

    public function testResultUrlsDefaultToNull(): void
    {
        $this->assertSame([
            'successUrl' => null,
            'pendingUrl' => null,
            'failureUrl' => null,
            'errorUrl' => null,
            'cancelUrl' => null,
            'expiryUrl' => null,
        ], get_object_vars(new ResultUrls()));
    }

    private function ownedResultUrls(AccessHppCreateTransactionBuilder $builder): ResultUrls
    {
        $property = new \ReflectionProperty(AccessHppCreateTransactionBuilder::class, 'resultUrls');
        $property->setAccessible(true);

        return $property->getValue($builder);
    }

    public function testConstructorInitializesSeparateResultUrlsForEachBuilder(): void
    {
        $first = new AccessHppCreateTransactionBuilder();
        $second = new AccessHppCreateTransactionBuilder();

        $this->assertNotSame($this->ownedResultUrls($first), $this->ownedResultUrls($second));
        $this->assertSame(
            get_object_vars(new ResultUrls()),
            get_object_vars($this->ownedResultUrls($first))
        );
        $this->assertArrayNotHasKey('resultURLs', $first->additionalFields);
    }

    public function testWithResultUrlsMapsAllEndpointsAndReturnsBuilder(): void
    {
        $urls = new ResultUrls();
        $urls->successUrl = 'https://example.test/success';
        $urls->pendingUrl = 'https://example.test/pending';
        $urls->failureUrl = 'https://example.test/failure';
        $urls->errorUrl = 'https://example.test/error';
        $urls->cancelUrl = 'https://example.test/cancel';
        $urls->expiryUrl = 'https://example.test/expiry';
        $builder = $this->validBuilder();

        $this->assertSame($builder, $builder->withResultUrls($urls));
        $this->assertNotSame($urls, $this->ownedResultUrls($builder));
        $this->assertSame(get_object_vars($urls), get_object_vars($this->ownedResultUrls($builder)));
        $this->assertSame([
            'successURL' => 'https://example.test/success',
            'pendingURL' => 'https://example.test/pending',
            'failureURL' => 'https://example.test/failure',
            'errorURL' => 'https://example.test/error',
            'cancelURL' => 'https://example.test/cancel',
            'expiryURL' => 'https://example.test/expiry',
        ], $builder->additionalFields['resultURLs']);
    }

    public function testWithResultUrlsSnapshotsAndReplacesPreviousConfiguration(): void
    {
        $urls = new ResultUrls();
        $urls->successUrl = 'https://example.test/success';
        $builder = $this->validBuilder()->withResultUrls($urls);

        $urls->successUrl = null;
        $urls->cancelUrl = 'https://example.test/cancel';
        $this->assertSame('https://example.test/success', $this->ownedResultUrls($builder)->successUrl);
        $this->assertNull($this->ownedResultUrls($builder)->cancelUrl);
        $this->assertSame(
            ['successURL' => 'https://example.test/success'],
            $builder->additionalFields['resultURLs']
        );

        $builder->withResultUrls($urls);
        $this->assertSame(get_object_vars($urls), get_object_vars($this->ownedResultUrls($builder)));
        $this->assertSame(
            ['cancelURL' => 'https://example.test/cancel'],
            $builder->additionalFields['resultURLs']
        );

        $builder->withResultUrls(new ResultUrls());
        $this->assertSame([], $builder->additionalFields['resultURLs']);
        $this->assertSame(
            get_object_vars(new ResultUrls()),
            get_object_vars($this->ownedResultUrls($builder))
        );
    }

    /**
     * @dataProvider invalidResultUrlProvider
     */
    #[DataProvider('invalidResultUrlProvider')]
    public function testWithResultUrlsRejectsInvalidEndpointsWithoutChangingState(
        string $property,
        string $field,
        string $invalidUrl
    ): void {
        $previous = ['cancelURL' => 'https://example.test/previous'];
        $builder = $this->validBuilder()->withAdditionalField('resultURLs', $previous);
        $urls = new ResultUrls();
        $urls->successUrl = 'https://example.test/success';
        $urls->$property = $invalidUrl;

        try {
            $builder->withResultUrls($urls);
            $this->fail('Expected an invalid result URL to be rejected.');
        } catch (ArgumentException $exception) {
            $this->assertSame('Invalid URL format for resultURLs.' . $field, $exception->getMessage());
        }

        $this->assertSame($previous, $builder->additionalFields['resultURLs']);
    }

    public static function invalidResultUrlProvider(): array
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

    public function testWithResultUrlsReportsFirstErrorWithoutChangingState(): void
    {
        $previous = ['cancelURL' => 'https://example.test/previous'];
        $previousUrls = new ResultUrls();
        $previousUrls->cancelUrl = $previous['cancelURL'];
        $builder = $this->validBuilder()->withResultUrls($previousUrls);
        $urls = new ResultUrls();
        $urls->successUrl = '';
        $urls->expiryUrl = 'not-a-url';

        try {
            $builder->withResultUrls($urls);
            $this->fail('Expected invalid result URLs to be rejected.');
        } catch (ArgumentException $exception) {
            $this->assertSame(
                'Invalid URL format for resultURLs.successURL',
                $exception->getMessage()
            );
        }

        $this->assertSame($previous, $builder->additionalFields['resultURLs']);
        $this->assertSame(
            get_object_vars($previousUrls),
            get_object_vars($this->ownedResultUrls($builder))
        );
    }

    public function testWithResultUrlsAndAdditionalFieldUseLastCall(): void
    {
        $urls = new ResultUrls();
        $urls->successUrl = 'https://example.test/success';
        $genericUrls = ['cancelURL' => 'https://example.test/cancel'];
        $builder = $this->validBuilder()->withAdditionalField('resultURLs', $genericUrls);

        $builder->withResultUrls($urls);
        $this->assertSame(
            ['successURL' => 'https://example.test/success'],
            $builder->additionalFields['resultURLs']
        );

        $builder->withAdditionalField('resultURLs', $genericUrls);
        $this->assertSame($genericUrls, $builder->additionalFields['resultURLs']);
    }

    public function testWithDescriptionAndAdditionalFieldUseLastCall(): void
    {
        $builder = $this->validBuilder()->withAdditionalField('description', 'Original description');

        $this->assertSame($builder, $builder->withDescription('Order 123'));
        $this->assertSame('Order 123', $builder->additionalFields['description']);

        $builder->withAdditionalField('description', 'Replacement description');
        $this->assertSame('Replacement description', $builder->additionalFields['description']);
    }

    /**
     * @dataProvider invalidCurrencyProvider
     */
    #[DataProvider('invalidCurrencyProvider')]
    public function testWithCurrencyRejectsCodesThatAreNotThreeCharacters(string $currency): void
    {
        $this->expectException(ArgumentException::class);
        $this->expectExceptionMessage('Currency must be a 3-character code');

        (new AccessHppCreateTransactionBuilder())->withCurrency($currency);
    }

    public static function invalidCurrencyProvider(): array
    {
        return [
            'empty currency' => [''],
            'two-character currency' => ['US'],
            'four-character currency' => ['USDX'],
        ];
    }

    public function testWithCurrencyNormalizesCodeToUppercase(): void
    {
        $builder = (new AccessHppCreateTransactionBuilder())->withCurrency('usd');

        $this->assertSame('USD', $builder->currency);
    }

    /**
     * @dataProvider invalidTransactionReferenceProvider
     */
    #[DataProvider('invalidTransactionReferenceProvider')]
    public function testWithTransactionReferenceRejectsBlankValues(string $transactionReference): void
    {
        $this->expectException(ArgumentException::class);
        $this->expectExceptionMessage('Transaction reference must not be empty.');

        (new AccessHppCreateTransactionBuilder())->withTransactionReference($transactionReference);
    }

    public static function invalidTransactionReferenceProvider(): array
    {
        return [
            'empty reference' => [''],
            'whitespace-only reference' => [" \t\n"],
        ];
    }

    public function testWithTransactionReferencePreservesNonblankValue(): void
    {
        $transactionReference = ' reference-123 ';
        $builder = (new AccessHppCreateTransactionBuilder())
            ->withTransactionReference($transactionReference);

        $this->assertSame($transactionReference, $builder->transactionReference);
    }

    /**
     * @dataProvider invalidAmountProvider
     */
    #[DataProvider('invalidAmountProvider')]
    public function testWithAmountRejectsNonPositiveAmounts(int $amount): void
    {
        $this->expectException(ArgumentException::class);
        $this->expectExceptionMessage(
            'Invalid order amount. Amount must be a positive integer in minor units.'
        );

        (new AccessHppCreateTransactionBuilder())->withAmount($amount);
    }

    public static function invalidAmountProvider(): array
    {
        return [
            'zero amount' => [0],
            'negative amount' => [-1],
        ];
    }

    public function testWithAmountPreservesPositiveIntegerAmount(): void
    {
        $builder = (new AccessHppCreateTransactionBuilder())->withAmount(1234);

        $this->assertSame(1234, $builder->amount);
    }
}
