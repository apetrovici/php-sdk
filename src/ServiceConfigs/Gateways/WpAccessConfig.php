<?php

namespace GlobalPayments\Api\ServiceConfigs\Gateways;

use GlobalPayments\Api\ConfiguredServices;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\Api\Gateways\WpAccessConnector;

/**
 * Minimal service configuration for the WP Access connector shell.
 *
 * This configuration only wires `WpAccessConnector` into the standard
 * `ConfiguredServices` pipeline so that unsupported operations (including
 * reporting) reach a predictable local rejection through
 * `ServicesContainer`. It intentionally has no authentication fields and no
 * default service URL: concrete Access authentication, endpoints, and
 * additional optional services (recurring, 3DS, Pay Facilitator, etc.) are
 * out of scope for this shell and require a separate, approved revision.
 */
class WpAccessConfig extends GatewayConfig
{
    public function __construct()
    {
        $this->gatewayProvider = GatewayProvider::WP_ACCESS;
    }

    public function configureContainer(ConfiguredServices $services)
    {
        $gateway = new WpAccessConnector();
        $gateway->requestLogger = $this->requestLogger;
        $gateway->webProxy = $this->webProxy;
        $gateway->dynamicHeaders = $this->dynamicHeaders;
        $gateway->environment = $this->environment;
        $gateway->timeout = $this->timeout;

        if (!empty($this->serviceUrl)) {
            $gateway->serviceUrl = $this->serviceUrl;
        }

        $services->gatewayConnector = $gateway;
        $services->reportingService = $gateway;
    }
}

