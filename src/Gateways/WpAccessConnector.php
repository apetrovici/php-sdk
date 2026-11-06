<?php

namespace GlobalPayments\Api\Gateways;

use GlobalPayments\Api\Builders\AuthorizationBuilder;
use GlobalPayments\Api\Builders\BaseBuilder;
use GlobalPayments\Api\Builders\ManagementBuilder;
use GlobalPayments\Api\Builders\ReportBuilder;
use GlobalPayments\Api\Builders\RequestBuilder\RequestBuilderFactory;
use GlobalPayments\Api\Entities\AccessWorldpayRequest;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\Api\Entities\Exceptions\UnsupportedTransactionException;
use GlobalPayments\Api\Mapping\AccessWorldpayMapping;
use GlobalPayments\Api\ServiceConfigs\Gateways\WpAccessConfig;

/**
 * WP Access RestGateway connector.
 *
 * Implements the standard SDK payment-gateway contract and the Access
 * capability/transport interfaces so that Access request builders can be
 * registered against `GatewayProvider::WP_ACCESS` and dispatched through the
 * standard `RequestBuilderFactory` pipeline (`processBuilderRequest()`).
 *
 * Every `IPaymentGateway` method remains unimplemented and fails predictably
 * with an `UnsupportedTransactionException`; only the HPP create-transaction
 * operation (dispatched via `processBuilderRequest()`) is implemented.
 */
class WpAccessConnector extends RestGateway implements IPaymentGateway, IAccessCapabilities, IAccessRequestGateway
{
    /**
     * Transaction types this connector actually supports via the legacy
     * `IPaymentGateway` surface.
     *
     * This set is intentionally empty: `IPaymentGateway` operations
     * (authorization, management, reporting, hosted serialization) are not
     * delivered by this connector. The only operation this connector
     * supports — creating an Access HPP transaction — is dispatched through
     * the Access-specific `processBuilderRequest()` seam instead, via
     * `AccessHppCreateTransactionBuilder`.
     *
     * @var int[]
     */
    private const SUPPORTED_TRANSACTION_TYPES = [];

    /**
     * Payment method types this connector actually supports.
     *
     * @var int[]
     */
    private const SUPPORTED_PAYMENT_METHOD_TYPES = [];

    private WpAccessConfig $config;

    public function __construct(WpAccessConfig $config)
    {
        parent::__construct();
        $this->config = $config;

        // Stable headers: fixed for the lifetime of this connector instance.
        // Content-Type matches the Accept header because this connector only
        // ever speaks the Access HPP vendor media type (AC1-op, AC1-auth).
        $this->contentType = 'application/vnd.worldpay.payment_pages-v1.hal+json';
        $this->headers['Accept'] = 'application/vnd.worldpay.payment_pages-v1.hal+json';
        $this->headers['Authorization'] = 'Basic ' . base64_encode($config->username . ':' . $config->password);
    }

    public function supportsTransactionType(int $transactionType): bool
    {
        return in_array($transactionType, self::SUPPORTED_TRANSACTION_TYPES, true);
    }

    public function supportsPaymentMethod(?int $paymentMethodType): bool
    {
        if ($paymentMethodType === null) {
            return false;
        }

        return in_array($paymentMethodType, self::SUPPORTED_PAYMENT_METHOD_TYPES, true);
    }

    public function supportsOpenBanking(): bool
    {
        return false;
    }

    public function processAuthorization(AuthorizationBuilder $builder)
    {
        throw new UnsupportedTransactionException(
            'WP Access does not support authorization requests through this method.'
        );
    }

    public function manageTransaction(ManagementBuilder $builder)
    {
        // With an empty SUPPORTED_TRANSACTION_TYPES set, this always rejects.
        $this->assertSupportedManagementType($builder);
    }

    public function processReport(ReportBuilder $builder)
    {
        throw new UnsupportedTransactionException(
            'WP Access does not support reporting requests in this connector.'
        );
    }

    public function serializeRequest(AuthorizationBuilder $builder)
    {
        throw new UnsupportedTransactionException(
            'WP Access hosted-payment serialization is not implemented in this connector.'
        );
    }

    /**
     * Extension seam for direct, pre-built `AccessWorldpayRequest` execution.
     *
     * This does not perform request-builder lookup; callers that want the
     * standard builder pipeline must use `processBuilderRequest()` instead.
     * This seam does not send any request by itself in this revision; no
     * caller currently constructs an `AccessWorldpayRequest` directly for a
     * supported operation.
     *
     * @param AccessWorldpayRequest $request
     *
     * @return mixed
     */
    public function executeRequest(AccessWorldpayRequest $request): mixed
    {
        throw new UnsupportedTransactionException(
            'Direct AccessWorldpayRequest execution is not implemented in this connector. '
            . 'Use the Access-specific fluent builders instead.'
        );
    }

    /**
     * Dispatches an Access-specific fluent builder (e.g.
     * `AccessHppCreateTransactionBuilder`) through the standard
     * `RequestBuilderFactory` pipeline for `GatewayProvider::WP_ACCESS`.
     *
     * @param BaseBuilder $builder
     *
     * @return mixed
     */
    public function processBuilderRequest(BaseBuilder $builder): mixed
    {
        $factory = new RequestBuilderFactory();
        $requestBuilder = $factory->getRequestBuilder($builder, GatewayProvider::WP_ACCESS);

        if ($requestBuilder === null) {
            throw new UnsupportedTransactionException(
                'WP Access does not support this request; no request builder is registered for it.'
            );
        }

        /** @var AccessWorldpayRequest $request */
        $request = $requestBuilder->buildRequest($builder, $this->config);

        foreach ($request->headers as $name => $value) {
            $this->headers[$name] = $value;
        }

        $rawResponse = $this->doTransaction(
            $request->httpVerb,
            $request->endpoint,
            $request->requestBody,
            $request->queryParams
        );

        return AccessWorldpayMapping::mapCreateTransactionResponse($rawResponse);
    }

    private function assertSupportedManagementType(ManagementBuilder $builder): void
    {
        if (!$this->supportsTransactionType((int) $builder->transactionType)) {
            throw new UnsupportedTransactionException(
                sprintf(
                    'WP Access does not support management transaction type %s in this connector.',
                    (string) $builder->transactionType
                )
            );
        }
    }
}

