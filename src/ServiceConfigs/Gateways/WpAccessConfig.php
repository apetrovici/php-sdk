<?php

namespace GlobalPayments\Api\ServiceConfigs\Gateways;

use GlobalPayments\Api\ConfiguredServices;
use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\Api\Entities\Enums\ServiceEndpoints;
use GlobalPayments\Api\Entities\Exceptions\ConfigurationException;
use GlobalPayments\Api\Gateways\WpAccessConnector;

/**
 * Service configuration for the WP Access connector.
 *
 * Owns all Worldpay Access credentials and the per-environment service URL.
 * Credentials and environment selection must never be placed in a request
 * builder; request builders only assemble operation-specific request data
 * and receive this config solely so the connector can resolve transport
 * concerns (service URL) consistently with other providers' configs
 * (see `GpApiConfig::resolveServiceUrl()`).
 */
class WpAccessConfig extends GatewayConfig
{
    /** HTTP Basic Auth username supplied by the Worldpay Implementation Manager. */
    public ?string $username = null;

    /** HTTP Basic Auth password supplied by the Worldpay Implementation Manager. */
    public ?string $password = null;

    public function __construct()
    {
        $this->gatewayProvider = GatewayProvider::WP_ACCESS;
    }

    public function configureContainer(ConfiguredServices $services)
    {
        if (empty($this->serviceUrl)) {
            $this->serviceUrl = $this->resolveServiceUrl();
        }

        $gateway = new WpAccessConnector($this);
        $gateway->requestLogger = $this->requestLogger;
        $gateway->webProxy = $this->webProxy;
        $gateway->dynamicHeaders = $this->dynamicHeaders;
        $gateway->environment = $this->environment;
        $gateway->timeout = $this->timeout;
        $gateway->serviceUrl = $this->serviceUrl;

        $services->gatewayConnector = $gateway;
        $services->reportingService = $gateway;
    }

    public function resolveServiceUrl(): string
    {
        if (!empty($this->serviceUrl)) {
            return $this->serviceUrl;
        }

        return ($this->environment === Environment::PRODUCTION)
            ? ServiceEndpoints::ACCESS_WORLDPAY_PRODUCTION
            : ServiceEndpoints::ACCESS_WORLDPAY_TEST;
    }

    public function validate(): void
    {
        parent::validate();

        if (empty($this->username) || empty($this->password)) {
            throw new ConfigurationException(
                'WP Access requires both username and password (HTTP Basic Auth credentials).'
            );
        }
    }
}
