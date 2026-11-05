<?php

namespace GlobalPayments\Api\Entities;

use GlobalPayments\Api\Entities\Exceptions\ArgumentException;

/**
 * Transport-neutral request data for the WP Access connector shell.
 *
 * The connector is responsible for translating this DTO to its transport.
 * Masking rules are kept on the request instance so consecutive requests
 * cannot inherit sensitive values from one another.
 *
 * This class is a structural carrier only. It validates that its own fields
 * are well-formed (non-blank verb/endpoint, safe header names/values, simple
 * scalar query values); it does not know or enforce any operation-specific
 * (Access wire contract) field requirements. Those belong to a future,
 * operation-specific request builder.
 */
class AccessWorldpayRequest
{
    public string $httpVerb;
    public string $endpoint;
    public ?string $requestBody;
    public ?array $queryParams;
    public array $headers;

    /** @var array<string, array{value:mixed, unmaskedLastChars:int, unmaskedFirstChars:int}> */
    private array $maskingRules = [];

    public function __construct(
        string $httpVerb,
        string $endpoint,
        ?string $requestBody = null,
        ?array $queryParams = null,
        array $headers = []
    ) {
        $this->httpVerb = strtoupper($httpVerb);
        $this->endpoint = $endpoint;
        $this->requestBody = $requestBody;
        $this->queryParams = $queryParams;
        $this->headers = $headers;

        $this->validate();
    }

    /**
     * Validates the current state of this carrier.
     *
     * This is called by the constructor and must also be called by any
     * future enabled transport adapter before sending the request, in case
     * a caller mutated a public property after construction.
     *
     * @throws ArgumentException
     * @return void
     */
    public function validate(): void
    {
        $this->validateHttpVerb();
        $this->validateEndpoint();
        $this->validateHeaders();
        $this->validateQueryParams();
    }

    private function validateHttpVerb(): void
    {
        if ($this->httpVerb === '' || !preg_match('/^[A-Z]+$/', $this->httpVerb)) {
            throw new ArgumentException(
                'AccessWorldpayRequest httpVerb must be a non-blank alphabetic token.'
            );
        }
    }

    private function validateEndpoint(): void
    {
        $endpoint = $this->endpoint;

        if ($endpoint === '' || trim($endpoint) === '') {
            throw new ArgumentException('AccessWorldpayRequest endpoint must not be blank.');
        }

        if ($this->containsControlCharacters($endpoint)) {
            throw new ArgumentException(
                'AccessWorldpayRequest endpoint must not contain control characters.'
            );
        }

        if (strpos($endpoint, '//') === 0 || strpos($endpoint, '://') !== false) {
            throw new ArgumentException(
                'AccessWorldpayRequest endpoint must be a relative path, not an absolute URL.'
            );
        }

        if (substr($endpoint, 0, 1) !== '/' || substr($endpoint, 0, 2) === '//') {
            throw new ArgumentException(
                'AccessWorldpayRequest endpoint must be a relative path beginning with a single "/".'
            );
        }
    }

    private function validateHeaders(): void
    {
        $seenNames = [];

        foreach ($this->headers as $name => $value) {
            if (!is_string($name) || trim($name) === '') {
                throw new ArgumentException(
                    'AccessWorldpayRequest header names must be non-blank strings.'
                );
            }

            if ($this->containsControlCharacters($name)) {
                throw new ArgumentException(
                    sprintf('AccessWorldpayRequest header name "%s" contains control characters.', $name)
                );
            }

            if (!is_string($value)) {
                throw new ArgumentException(
                    sprintf('AccessWorldpayRequest header "%s" value must be a string.', $name)
                );
            }

            if ($this->containsControlCharacters($value)) {
                throw new ArgumentException(
                    sprintf('AccessWorldpayRequest header "%s" value contains control characters.', $name)
                );
            }

            $normalizedName = strtolower($name);
            if (isset($seenNames[$normalizedName])) {
                throw new ArgumentException(
                    sprintf('AccessWorldpayRequest contains duplicate header "%s".', $name)
                );
            }
            $seenNames[$normalizedName] = true;
        }
    }

    private function validateQueryParams(): void
    {
        if ($this->queryParams === null) {
            return;
        }

        foreach ($this->queryParams as $key => $value) {
            if (!is_string($key) && !is_int($key)) {
                throw new ArgumentException('AccessWorldpayRequest query parameter keys must be strings or integers.');
            }

            if ($value !== null && !is_scalar($value)) {
                throw new ArgumentException(
                    sprintf(
                        'AccessWorldpayRequest query parameter "%s" must be a scalar or null value.',
                        (string) $key
                    )
                );
            }
        }
    }

    private function containsControlCharacters(string $value): bool
    {
        return (bool) preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $value);
    }

    public function addMaskingRule(
        string $key,
        mixed $value,
        int $unmaskedLastChars = 0,
        int $unmaskedFirstChars = 0
    ): self {
        $this->maskingRules[$key] = [
            'value' => $value,
            'unmaskedLastChars' => $unmaskedLastChars,
            'unmaskedFirstChars' => $unmaskedFirstChars,
        ];

        return $this;
    }

    /** @return array<string, string> */
    public function getMaskedValues(): array
    {
        $masked = [];
        foreach ($this->maskingRules as $key => $rule) {
            if ($rule['value'] === null || is_array($rule['value']) || is_object($rule['value'])) {
                continue;
            }

            $value = (string) $rule['value'];
            $last = $rule['unmaskedLastChars'];
            $first = $rule['unmaskedFirstChars'];
            $visible = $first + $last;
            if ($visible >= strlen($value)) {
                $last = 0;
                $first = 0;
            }

            $masked[$key] = substr($value, 0, $first)
                . str_repeat('X', max(0, strlen($value) - $first - $last))
                . ($last > 0 ? substr($value, -$last) : '');
        }

        return $masked;
    }

    public function getMaskedHeaders(): array
    {
        $masked = $this->headers;
        foreach ($masked as $name => $value) {
            if (preg_match('/authorization|token|secret|password|credential/i', (string) $name)) {
                $masked[$name] = '[REDACTED]';
            }
        }

        return $masked;
    }
}

