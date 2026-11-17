<?php

namespace GlobalPayments\Api\Entities\WpAccess;

/**
 * Optional customer redirect URLs for Access HPP payment results.
 * Null endpoints are omitted from the request.
 */
class ResultUrls
{
    /**
     * @var string|null Customer redirect URL for a successful payment.
     */
    public ?string $successUrl = null;

    /**
     * @var string|null Customer redirect URL for a pending payment transaction.
     */
    public ?string $pendingUrl = null;

    /**
     * @var string|null Customer redirect URL for a failed payment.
     */
    public ?string $failureUrl = null;

    /**
     * @var string|null Customer redirect URL for an erroneous payment.
     */
    public ?string $errorUrl = null;

    /**
     * @var string|null Customer redirect URL for when your customer cancels a transaction.
     */
    public ?string $cancelUrl = null;

    /**
     * @var string|null Customer redirect URL for when a customer leaves the payment transaction uncompleted
     * within the maximum allowed time frame
     */
    public ?string $expiryUrl = null;

    /**
     * @return string[] Validation errors, empty when all supplied URLs are valid.
     */
    public function validate(): array
    {
        $errors = [];
        foreach ([
            'successURL' => $this->successUrl,
            'pendingURL' => $this->pendingUrl,
            'failureURL' => $this->failureUrl,
            'errorURL' => $this->errorUrl,
            'cancelURL' => $this->cancelUrl,
            'expiryURL' => $this->expiryUrl,
        ] as $field => $url) {
            if ($url !== null && !filter_var($url, FILTER_VALIDATE_URL)) {
                $errors[] = 'Invalid URL format for resultURLs.' . $field;
            }
        }

        return $errors;
    }
}
