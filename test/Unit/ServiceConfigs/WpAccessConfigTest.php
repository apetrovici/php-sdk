<?php

namespace GlobalPayments\Api\Tests\Unit\ServiceConfigs;

use GlobalPayments\Api\Builders\TransactionReportBuilder;
use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\Api\Entities\Enums\ReportType;
use GlobalPayments\Api\Entities\Enums\ServiceEndpoints;
use GlobalPayments\Api\Entities\Exceptions\ConfigurationException;
use GlobalPayments\Api\Entities\Exceptions\UnsupportedTransactionException;
use GlobalPayments\Api\Gateways\WpAccessConnector;
use GlobalPayments\Api\ServiceConfigs\Gateways\WpAccessConfig;
use GlobalPayments\Api\ServicesContainer;
use PHPUnit\Framework\TestCase;

/**
 * Tests for WpAccessConfig: credential validation, per-environment URL
 * resolution, and wiring into ServicesContainer. No network call, real
 * credential, or live environment is used.
 */
class WpAccessConfigTest extends TestCase
{
    private function syntheticConfig(): WpAccessConfig
    {
        $config = new WpAccessConfig();
        $config->username = 'synthetic-user';
        $config->password = 'synthetic-pass';

        return $config;
    }

    public function testValidateThrowsConfigurationExceptionWhenCredentialsMissing(): void
    {
        $config = new WpAccessConfig();

        $this->expectException(ConfigurationException::class);

        $config->validate();
    }

    public function testValidatePassesWhenCredentialsPresent(): void
    {
        $config = $this->syntheticConfig();

        $config->validate();

        $this->assertTrue($config->validated);
    }

    public function testResolveServiceUrlReturnsTestUrlByDefault(): void
    {
        $config = $this->syntheticConfig();

        $this->assertSame(Environment::TEST, $config->environment);
        $this->assertSame(ServiceEndpoints::ACCESS_WORLDPAY_TEST, $config->resolveServiceUrl());
    }

    public function testResolveServiceUrlReturnsProductionUrlForProductionEnvironment(): void
    {
        $config = $this->syntheticConfig();
        $config->environment = Environment::PRODUCTION;

        $this->assertSame(ServiceEndpoints::ACCESS_WORLDPAY_PRODUCTION, $config->resolveServiceUrl());
    }

    public function testResolveServiceUrlRespectsExplicitOverride(): void
    {
        $config = $this->syntheticConfig();
        $config->serviceUrl = 'https://custom.example.test';

        $this->assertSame('https://custom.example.test', $config->resolveServiceUrl());
    }

    public function testConfigureContainerResolvesWpAccessConnectorByName(): void
    {
        $configName = 'wp-access-test-' . __FUNCTION__;

        ServicesContainer::configureService($this->syntheticConfig(), $configName);

        $client = ServicesContainer::instance()->getClient($configName);

        $this->assertInstanceOf(WpAccessConnector::class, $client);
    }

    public function testConfigureContainerSetsExpectedGatewayProvider(): void
    {
        $config = $this->syntheticConfig();

        $this->assertSame(GatewayProvider::WP_ACCESS, $config->gatewayProvider);
    }

    public function testSeparateNamedConfigurationsDoNotShareConnectorState(): void
    {
        $firstConfigName = 'wp-access-test-first';
        $secondConfigName = 'wp-access-test-second';

        ServicesContainer::configureService($this->syntheticConfig(), $firstConfigName);
        ServicesContainer::configureService($this->syntheticConfig(), $secondConfigName);

        $firstClient = ServicesContainer::instance()->getClient($firstConfigName);
        $secondClient = ServicesContainer::instance()->getClient($secondConfigName);

        $this->assertNotSame($firstClient, $secondClient);
    }

    public function testReportingReachesPredictableLocalRejection(): void
    {
        $configName = 'wp-access-test-' . __FUNCTION__;

        ServicesContainer::configureService($this->syntheticConfig(), $configName);
        $client = ServicesContainer::instance()->getClient($configName);

        $builder = new TransactionReportBuilder(ReportType::TRANSACTION_DETAIL);

        $this->expectException(UnsupportedTransactionException::class);

        $client->processReport($builder);
    }
}

