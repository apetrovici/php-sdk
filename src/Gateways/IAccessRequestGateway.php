<?php

namespace GlobalPayments\Api\Gateways;

use GlobalPayments\Api\Builders\BaseBuilder;
use GlobalPayments\Api\Entities\AccessWorldpayRequest;

/**
 * Transport extension seam for the WP Access connector.
 *
 * `executeRequest()` hands an already-built, validated `AccessWorldpayRequest`
 * to the connector for execution; it defines no wire-level behavior itself.
 *
 * `processBuilderRequest()` is the builder-aware dispatch entry point used by
 * Access-specific fluent builders (e.g. `AccessHppCreateTransactionBuilder`).
 * Implementations must resolve the matching `IRequestBuilder` for the given
 * `$builder` (via `RequestBuilderFactory` against `GatewayProvider::WP_ACCESS`),
 * build the wire request, execute it, and map the response. If no matching
 * request builder is registered, implementations must throw
 * `UnsupportedTransactionException` rather than silently returning a
 * successful-looking result.
 */
interface IAccessRequestGateway
{
    /**
     * Executes a transport-neutral Access request.
     *
     * Until an approved operational revision enables real transport for a
     * given caller, concrete implementations must reject this call
     * predictably (e.g. by throwing an `UnsupportedTransactionException`)
     * rather than silently returning a successful-looking result.
     *
     * @param AccessWorldpayRequest $request
     *
     * @return mixed
     */
    public function executeRequest(AccessWorldpayRequest $request): mixed;

    /**
     * Dispatches an Access-specific fluent builder through the standard
     * request-builder factory pipeline for `GatewayProvider::WP_ACCESS`.
     *
     * @param BaseBuilder $builder
     *
     * @return mixed
     */
    public function processBuilderRequest(BaseBuilder $builder): mixed;
}


