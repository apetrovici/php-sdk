<?php

namespace GlobalPayments\Api\Gateways;

/**
 * Describes the capability-query contract for the WP Access connector shell.
 *
 * Implementations must keep capability reporting consistent with actual
 * dispatch behavior. Reporting a supported transaction type or payment
 * method that the connector does not actually execute is not permitted.
 */
interface IAccessCapabilities
{
    /**
     * Determines whether the connector supports the given transaction type.
     *
     * @param int $transactionType One of the `TransactionType` constants.
     *
     * @return bool
     */
    public function supportsTransactionType(int $transactionType): bool;

    /**
     * Determines whether the connector supports the given payment method type.
     *
     * A null value represents "no specific payment method type supplied" and
     * must be handled explicitly by the implementation; it is not treated as
     * an unknown/invalid value.
     *
     * @param int|null $paymentMethodType One of the `PaymentMethodType` constants, or null.
     *
     * @return bool
     */
    public function supportsPaymentMethod(?int $paymentMethodType): bool;
}

