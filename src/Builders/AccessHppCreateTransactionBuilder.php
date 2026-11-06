<?php

namespace GlobalPayments\Api\Builders;

use GlobalPayments\Api\Entities\Exceptions\BuilderException;
use GlobalPayments\Api\Entities\Exceptions\UnsupportedTransactionException;
use GlobalPayments\Api\Gateways\IAccessRequestGateway;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\Api\Utils\GenerationUtils;

/**
 * Fluent builder for the Worldpay Access HPP "Create a new transaction"
 * operation (`POST /payment_pages`).
 *
 * Required fields (`gp-php-sdk-fork/skills/wpaccessapi/SKILL.md:82-721`):
 * `merchant.entity`, `narrative.line1`, `transactionReference`, `value.amount`.
 * `value.currency` is treated as required by this builder even though the
 * reviewed contract text does not explicitly flag it `required` (a real
 * payment amount is meaningless without a currency); see change-file gap G14.
 *
 * Less common optional top-level objects (billingAddress, createToken,
 * customerAgreement, fraud, fundsTransfer, hostedCustomization,
 * hostedProperties, locale, paymentInstrument, resultURLs, riskData,
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
        $this->transactionReference = $transactionReference;
        return $this;
    }

    public function withAmount(int $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function withCurrency(string $currency): self
    {
        $this->currency = $currency;
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

