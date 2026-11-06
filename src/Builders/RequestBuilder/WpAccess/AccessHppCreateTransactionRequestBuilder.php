<?php

namespace GlobalPayments\Api\Builders\RequestBuilder\WpAccess;

use GlobalPayments\Api\Builders\AccessHppCreateTransactionBuilder;
use GlobalPayments\Api\Builders\BaseBuilder;
use GlobalPayments\Api\Entities\AccessWorldpayRequest;
use GlobalPayments\Api\Entities\Exceptions\ArgumentException;
use GlobalPayments\Api\Entities\Exceptions\UnsupportedTransactionException;
use GlobalPayments\Api\Entities\IRequestBuilder;
use GlobalPayments\Api\ServiceConfigs\Gateways\WpAccessConfig;
use GlobalPayments\Api\Utils\ArrayUtils;

/**
 * Builds the wire request for the Worldpay Access HPP "Create a new
 * transaction" operation (`POST /payment_pages`).
 *
 * Request schema evidence: `gp-php-sdk-fork/skills/wpaccessapi/SKILL.md:58-721`.
 * Registered against `GatewayProvider::WP_ACCESS` in `RequestBuilderFactory`.
 */
class AccessHppCreateTransactionRequestBuilder implements IRequestBuilder
{
    public function buildRequest(BaseBuilder $builder, mixed $config): mixed
    {
        if (!$builder instanceof AccessHppCreateTransactionBuilder) {
            throw new ArgumentException(
                'AccessHppCreateTransactionRequestBuilder can only build requests for AccessHppCreateTransactionBuilder.'
            );
        }

        if (!$config instanceof WpAccessConfig) {
            throw new ArgumentException(
                'AccessHppCreateTransactionRequestBuilder requires a WpAccessConfig instance.'
            );
        }

        $required = [
            'merchant' => ['entity' => $builder->merchantEntity],
            'narrative' => ['line1' => $builder->narrativeLine1],
            'transactionReference' => $builder->transactionReference,
            'value' => [
                'amount' => $builder->amount,
                'currency' => $builder->currency,
            ],
        ];

        $body = array_merge($builder->additionalFields, $required);
        $body = ArrayUtils::array_remove_empty($body);
        $json = json_encode($body, JSON_UNESCAPED_SLASHES);

        $headers = [
            'WP-CorrelationId' => $builder->correlationId,
        ];
        if (!empty($builder->userAgent)) {
            $headers['User-Agent'] = $builder->userAgent;
        }

        return new AccessWorldpayRequest('POST', '/payment_pages', $json, null, $headers);
    }

    public function buildRequestFromJson(mixed $jsonRequest, mixed $config): mixed
    {
        throw new UnsupportedTransactionException(
            'AccessHppCreateTransactionRequestBuilder does not support buildRequestFromJson in this version.'
        );
    }

    public static function canProcess(?BaseBuilder $builder = null): bool
    {
        return $builder instanceof AccessHppCreateTransactionBuilder;
    }
}

