<?php

namespace GlobalPayments\Api\Gateways;

use GlobalPayments\Api\Builders\AuthorizationBuilder;
use GlobalPayments\Api\Builders\ManagementBuilder;
use GlobalPayments\Api\Builders\ReportBuilder;
use GlobalPayments\Api\Entities\AccessWorldpayRequest;
use GlobalPayments\Api\Entities\Exceptions\UnsupportedTransactionException;

/**
 * WP Access RestGateway connector shell.
 *
 * This connector satisfies the standard SDK payment-gateway contract and the
 * Access capability/transport interfaces so that future, operation-specific
 * request builders can be registered against `GatewayProvider::WP_ACCESS`
 * without further changes to this class's public surface.
 *
 * This shell does not implement any concrete Access operation. Every method
 * on `IPaymentGateway`, and direct DTO execution, fails predictably with an
 * `UnsupportedTransactionException` until an approved, operation-specific
 * revision enables real behavior. No network request is made by this class.
 */
class WpAccessConnector extends RestGateway implements IPaymentGateway, IAccessCapabilities, IAccessRequestGateway
{
    /**
     * Transaction types this shell actually supports.
     *
     * This set is intentionally empty: this feature delivers the connector
     * shell, capability interfaces, request DTO, and factory registration
     * only. Concrete Access operations are added by a future, separately
     * approved change file.
     *
     * @var int[]
     */
    private const SUPPORTED_TRANSACTION_TYPES = [];

    /**
     * Payment method types this shell actually supports.
     *
     * @var int[]
     */
    private const SUPPORTED_PAYMENT_METHOD_TYPES = [];

    public function __construct()
    {
        parent::__construct('application/json');
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
            'WP Access does not support authorization requests in this connector shell.'
        );
    }

    public function manageTransaction(ManagementBuilder $builder)
    {
        // With an empty SUPPORTED_TRANSACTION_TYPES set, this always rejects.
        // A future, approved revision adds supported types and routes the
        // matched ones to a registered WP_ACCESS request builder instead.
        $this->assertSupportedManagementType($builder);
    }

    public function processReport(ReportBuilder $builder)
    {
        throw new UnsupportedTransactionException(
            'WP Access does not support reporting requests in this connector shell.'
        );
    }

    public function serializeRequest(AuthorizationBuilder $builder)
    {
        throw new UnsupportedTransactionException(
            'WP Access hosted-payment serialization is not implemented in this connector shell.'
        );
    }

    /**
     * Extension seam for a future, approved transport implementation.
     *
     * This shell rejects every call predictably; it does not send any
     * request and does not act as a bypass around capability restrictions.
     *
     * @param AccessWorldpayRequest $request
     *
     * @return mixed
     */
    public function executeRequest(AccessWorldpayRequest $request): mixed
    {
        throw new UnsupportedTransactionException(
            'WP Access request execution is not implemented in this connector shell.'
        );
    }

    private function assertSupportedManagementType(ManagementBuilder $builder): void
    {
        if (!$this->supportsTransactionType((int) $builder->transactionType)) {
            throw new UnsupportedTransactionException(
                sprintf(
                    'WP Access does not support management transaction type %s in this connector shell.',
                    (string) $builder->transactionType
                )
            );
        }
    }
}


