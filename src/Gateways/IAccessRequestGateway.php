<?php

namespace GlobalPayments\Api\Gateways;

use GlobalPayments\Api\Entities\AccessWorldpayRequest;

/**
 * Transport extension seam for the WP Access connector shell.
 *
 * This interface defines how a already-built, validated `AccessWorldpayRequest`
 * is handed to the connector for execution. It intentionally does not define
 * any wire-level behavior (headers, authentication, status handling); that is
 * the responsibility of the concrete connector once an approved operation
 * revision enables transport.
 */
interface IAccessRequestGateway
{
    /**
     * Executes a transport-neutral Access request.
     *
     * Until an approved operational revision enables real transport, concrete
     * implementations must reject this call predictably (e.g. by throwing an
     * `UnsupportedTransactionException`) rather than silently returning a
     * successful-looking result.
     *
     * @param AccessWorldpayRequest $request
     *
     * @return mixed
     */
    public function executeRequest(AccessWorldpayRequest $request): mixed;
}

