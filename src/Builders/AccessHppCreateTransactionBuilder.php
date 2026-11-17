<?php

namespace GlobalPayments\Api\Builders;

use GlobalPayments\Api\Entities\Exceptions\ArgumentException;
use GlobalPayments\Api\Entities\Exceptions\BuilderException;
use GlobalPayments\Api\Entities\Exceptions\UnsupportedTransactionException;
use GlobalPayments\Api\Entities\WpAccess\ResultUrls;
use GlobalPayments\Api\Gateways\IAccessRequestGateway;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\Api\Utils\GenerationUtils;

/**
 * Fluent builder for the Worldpay Access HPP "Create a new transaction"
 * operation (`POST /payment_pages`).
 *
 * Less common optional top-level objects (billingAddress, createToken,
 * customerAgreement, fraud, fundsTransfer, hostedCustomization,
 * hostedProperties, locale, paymentInstrument, riskData,
 * settlement, threeDS) are accepted via `withAdditionalField()` as a
 * deliberate simplification instead of a dedicated setter per nested object;
 * this builder does not validate their internal structure.
 */
class AccessHppCreateTransactionBuilder extends BaseBuilder
{
    public ?string $merchantEntity = null;
    public ?string $narrativeLine1 = null;
    public ?string $transactionReference = null;
    public ?int $amount = null;
    public ?string $currency = null;
    public ?string $correlationId = null;
    public ?string $userAgent = null;

    /** @var array<string, mixed> */
    public array $additionalFields = [];

    /**
     * Last typed configuration; additionalFields holds the outbound snapshot.
     *
     * @var ResultUrls
     */
    private ResultUrls $resultUrls;

    public function __construct()
    {
        parent::__construct();
        $this->resultUrls = new ResultUrls();
    }

    public function withMerchantEntity(string $merchantEntity): self
    {
        $this->merchantEntity = $merchantEntity;
        return $this;
    }

    public function withNarrativeLine1(string $narrativeLine1): self
    {
        $this->narrativeLine1 = $narrativeLine1;
        return $this;
    }

    public function withTransactionReference(string $transactionReference): self
    {
        if (trim($transactionReference) === '') {
            throw new ArgumentException(
                'Transaction reference must not be empty.'
            );
        }

        $this->transactionReference = $transactionReference;
        return $this;
    }

    public function withAmount(int $amount): self
    {
        if ($amount <= 0) {
            throw new ArgumentException(
                'Invalid order amount. Amount must be a positive integer in minor units.'
            );
        }

        $this->amount = $amount;
        return $this;
    }

    public function withCurrency(string $currency): self
    {
        if (strlen($currency) !== 3) {
            throw new ArgumentException('Currency must be a 3-character code');
        }

        $this->currency = strtoupper($currency);
        return $this;
    }

    /**
     * Sets the `WP-CorrelationId` header value for this request.
     *
     * If not supplied, one is generated via `GenerationUtils::getGuid()`.
     */
    public function withCorrelationId(string $correlationId): self
    {
        $this->correlationId = $correlationId;
        return $this;
    }

    public function withUserAgent(string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * Sets optional text displayed to the customer on the payment pages.
     */
    public function withDescription(string $description): self
    {
        return $this->withAdditionalField('description', $description);
    }

    /**
     * Replaces the optional result URLs with a validated snapshot.
     * Null endpoints are omitted; an all-null object clears previous URLs.
     * The last call to this method or withAdditionalField('resultURLs', ...) wins.
     *
     * $urls = new ResultUrls();
     * $urls->successUrl = 'https://example.test/payment/success';
     * $builder->withResultUrls($urls);
     *
     * Validation rules are defined by ResultUrls::validate().
     *
     * @throws ArgumentException When a supplied endpoint is not a valid URL.
     */
    public function withResultUrls(ResultUrls $resultUrls): self
    {
        $errors = $resultUrls->validate();
        if ($errors !== []) {
            throw new ArgumentException($errors[0]);
        }

        $this->resultUrls->successUrl = $resultUrls->successUrl;
        $this->resultUrls->pendingUrl = $resultUrls->pendingUrl;
        $this->resultUrls->failureUrl = $resultUrls->failureUrl;
        $this->resultUrls->errorUrl = $resultUrls->errorUrl;
        $this->resultUrls->cancelUrl = $resultUrls->cancelUrl;
        $this->resultUrls->expiryUrl = $resultUrls->expiryUrl;

        $mappedUrls = [];
        foreach ([
            'successURL' => $this->resultUrls->successUrl,
            'pendingURL' => $this->resultUrls->pendingUrl,
            'failureURL' => $this->resultUrls->failureUrl,
            'errorURL' => $this->resultUrls->errorUrl,
            'cancelURL' => $this->resultUrls->cancelUrl,
            'expiryURL' => $this->resultUrls->expiryUrl,
        ] as $field => $url) {
            if ($url === null) {
                continue;
            }

            $mappedUrls[$field] = $url;
        }

        return $this->withAdditionalField('resultURLs', $mappedUrls);
    }

    /**
     * Adds (or overwrites) a top-level optional request field, e.g.
     * `withAdditionalField('resultURLs', ['successURL' => '...'])`.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return self
     */
    public function withAdditionalField(string $key, mixed $value): self
    {
        $this->additionalFields[$key] = $value;
        return $this;
    }

    protected function setupValidations()
    {
        // Required-field validation is performed explicitly in execute()
        // rather than through the TransactionType-keyed Validations system,
        // which this operation does not use.
    }

    public function execute($configName = 'default')
    {
        $this->validateRequiredFields();

        if (empty($this->correlationId)) {
            $this->correlationId = GenerationUtils::getGuid();
        }

        $client = ServicesContainer::instance()->getClient($configName);
        if (!$client instanceof IAccessRequestGateway) {
            throw new UnsupportedTransactionException(
                'The configured gateway does not support WP Access HPP transaction creation.'
            );
        }

        return $client->processBuilderRequest($this);
    }

    private function validateRequiredFields(): void
    {
        $missing = [];
        foreach ([
            'merchantEntity' => $this->merchantEntity,
            'narrativeLine1' => $this->narrativeLine1,
            'transactionReference' => $this->transactionReference,
            'amount' => $this->amount,
            'currency' => $this->currency,
        ] as $field => $value) {
            if ($value === null || $value === '') {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new BuilderException(
                sprintf(
                    'AccessHppCreateTransactionBuilder is missing required field(s): %s.',
                    implode(', ', $missing)
                )
            );
        }
    }
}
