<?php

namespace GlobalPayments\Api\Entities\AccessWorldpay;

/**
 * Normalized response for the Worldpay Access HPP "Create a new transaction"
 * operation (`POST /payment_pages`).
 *
 * Field mapping is documented in
 * `gp-php-sdk-fork/skills/wpaccessapi/SKILL.md` (lines 772-789):
 * - `_links.self.href` -> `selfLink`
 * - `url` -> `redirectUrl`
 */
class HostedPaymentPageResponse
{
    /**
     * Link to query the Payment Queries API by `transactionReference` to
     * retrieve the `paymentId`.
     *
     * @var string|null
     */
    public ?string $selfLink = null;

    /**
     * The URL where the customer should be redirected to complete payment.
     *
     * @var string|null
     */
    public ?string $redirectUrl = null;

    /**
     * The decoded raw response body, retained for forward compatibility with
     * response fields this normalized shape does not yet expose.
     *
     * @var array
     */
    public array $rawResponse = [];
}

