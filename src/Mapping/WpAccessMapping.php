<?php

namespace GlobalPayments\Api\Mapping;

use GlobalPayments\Api\Entities\WpAccess\AccessHPPResponse;
use GlobalPayments\Api\Entities\Exceptions\ApiException;

/**
 * Response mapping for Worldpay Access HPP operations.
 */
class WpAccessMapping
{
    /**
     * Maps a raw 200 response body for `POST /payment_pages` onto a
     * normalized `AccessHPPResponse`.
     *
     * Field mapping is documented in
     * `gp-php-sdk-fork/skills/wpaccessapi/SKILL.md` (lines 772-789).
     *
     * @param string $rawResponse
     *
     * @throws ApiException when the response is not valid JSON
     *
     * @return AccessHPPResponse
     */
    public static function mapCreateTransactionResponse(string $rawResponse): AccessHPPResponse
    {
        $parsed = json_decode($rawResponse);
        if (json_last_error() !== JSON_ERROR_NONE || !is_object($parsed)) {
            throw new ApiException('Unable to parse WP Access create-transaction response as JSON.');
        }

        $response = new AccessHPPResponse();
        $response->rawResponse = json_decode($rawResponse, true) ?? [];
        $response->selfLink = $parsed->_links->self->href ?? null;
        $response->redirectUrl = $parsed->url ?? null;

        return $response;
    }
}
