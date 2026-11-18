<?php

namespace GlobalPayments\Api\Tests\Unit\Builders\RequestBuilder;

use GlobalPayments\Api\Builders\AccessHppCreateTransactionBuilder;
use GlobalPayments\Api\Builders\ManagementBuilder;
use GlobalPayments\Api\Builders\RequestBuilder\GpApi\GpApiManagementRequestBuilder;
use GlobalPayments\Api\Builders\RequestBuilder\RequestBuilderFactory;
use GlobalPayments\Api\Builders\RequestBuilder\WpAccess\AccessHppCreateTransactionRequestBuilder;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\Api\Entities\Enums\TransactionType;
use GlobalPayments\Api\PaymentMethods\TransactionReference;
use PHPUnit\Framework\TestCase;

/**
 * Regression/foundation tests for RequestBuilderFactory.
 *
 * Confirms existing provider routing is unaffected by the
 * GatewayProvider::WP_ACCESS slot, and that the slot now resolves the
 * registered HPP create-transaction request builder for a matching builder
 * while still returning null for non-matching builders.
 */
class RequestBuilderFactoryTest extends TestCase
{
    private RequestBuilderFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new RequestBuilderFactory();
    }

    public function testExistingGpApiManagementRoutingIsUnaffected(): void
    {
        $builder = new ManagementBuilder(TransactionType::CAPTURE, new TransactionReference());

        $result = $this->factory->getRequestBuilder($builder, GatewayProvider::GP_API);

        $this->assertInstanceOf(GpApiManagementRequestBuilder::class, $result);
    }

    public function testUnknownProviderReturnsNull(): void
    {
        $builder = new ManagementBuilder(TransactionType::CAPTURE, new TransactionReference());

        $result = $this->factory->getRequestBuilder($builder, 'NOT-A-REGISTERED-PROVIDER');

        $this->assertNull($result);
    }

    public function testWpAccessProviderReturnsNullForNonMatchingBuilder(): void
    {
        $builder = new ManagementBuilder(TransactionType::CAPTURE, new TransactionReference());

        $result = $this->factory->getRequestBuilder($builder, GatewayProvider::WP_ACCESS);

        $this->assertNull($result);
    }

    public function testWpAccessProviderReturnsHppCreateTransactionBuilderForMatchingBuilder(): void
    {
        $builder = new AccessHppCreateTransactionBuilder();

        $result = $this->factory->getRequestBuilder($builder, GatewayProvider::WP_ACCESS);

        $this->assertInstanceOf(AccessHppCreateTransactionRequestBuilder::class, $result);
    }

    public function testWpAccessProviderSlotContainsExactlyOneRegisteredBuilder(): void
    {
        $reflection = new \ReflectionClass(RequestBuilderFactory::class);
        $processesProperty = $reflection->getProperty('processes');
        $processesProperty->setAccessible(true);
        $processes = $processesProperty->getValue();

        $this->assertArrayHasKey(GatewayProvider::WP_ACCESS, $processes);
        $this->assertSame(
            [AccessHppCreateTransactionRequestBuilder::class],
            $processes[GatewayProvider::WP_ACCESS]
        );
    }
}

