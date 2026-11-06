<?php

namespace GlobalPayments\Api\Tests\Unit\Entities;

use GlobalPayments\Api\Entities\AccessWorldpayRequest;
use GlobalPayments\Api\Entities\Exceptions\ArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Shell acceptance tests for the AccessWorldpayRequest transport-neutral DTO.
 *
 * These tests cover V5-V7 of the approved SDK Change v2 test plan. The DTO
 * is a structural carrier only; these tests do not assert any Access wire
 * contract and use only synthetic values.
 */
class AccessWorldpayRequestTest extends TestCase
{
    public function testConstructorNormalizesVerbAndPreservesFieldsExactly(): void
    {
        $queryParams = ['limit' => 0, 'includeArchived' => false, 'cursor' => null];
        $headers = ['X-Custom-Header' => 'synthetic-value'];

        $request = new AccessWorldpayRequest('post', '/synthetic/resource', '{"a":1}', $queryParams, $headers);

        $this->assertSame('POST', $request->httpVerb);
        $this->assertSame('/synthetic/resource', $request->endpoint);
        $this->assertSame('{"a":1}', $request->requestBody);
        $this->assertSame($queryParams, $request->queryParams);
        $this->assertSame($headers, $request->headers);
    }

    public function testQueryParamsRetainFalsyScalarValues(): void
    {
        $request = new AccessWorldpayRequest('GET', '/synthetic/resource', null, [
            'zero' => 0,
            'falseFlag' => false,
            'nullValue' => null,
        ]);

        $this->assertArrayHasKey('zero', $request->queryParams);
        $this->assertSame(0, $request->queryParams['zero']);
        $this->assertArrayHasKey('falseFlag', $request->queryParams);
        $this->assertFalse($request->queryParams['falseFlag']);
        $this->assertArrayHasKey('nullValue', $request->queryParams);
        $this->assertNull($request->queryParams['nullValue']);
    }

    /**
     * @dataProvider bodyVariantProvider
     */
    public function testBodyVariantsAreDistinguishedAndPreservedAsOpaqueStrings(?string $body): void
    {
        $request = new AccessWorldpayRequest('POST', '/synthetic/resource', $body);

        $this->assertSame($body, $request->requestBody);
    }

    public static function bodyVariantProvider(): array
    {
        return [
            'null body' => [null],
            'empty string body' => [''],
            'json object body' => ['{}'],
            'json array body' => ['[]'],
            'malformed json is preserved as opaque data' => ['{not valid json'],
        ];
    }

    /**
     * @dataProvider invalidConstructionProvider
     */
    public function testConstructorRejectsInvalidStructuralInput(
        string $httpVerb,
        string $endpoint,
        ?array $queryParams,
        array $headers
    ): void {
        $this->expectException(ArgumentException::class);

        new AccessWorldpayRequest($httpVerb, $endpoint, null, $queryParams, $headers);
    }

    public static function invalidConstructionProvider(): array
    {
        return [
            'blank verb' => ['', '/synthetic', null, []],
            'whitespace verb' => ['   ', '/synthetic', null, []],
            'blank endpoint' => ['POST', '', null, []],
            'whitespace endpoint' => ['POST', '   ', null, []],
            'absolute endpoint with scheme' => ['POST', 'https://example.test/synthetic', null, []],
            'protocol-relative endpoint' => ['POST', '//example.test/synthetic', null, []],
            'endpoint missing leading slash' => ['POST', 'synthetic/resource', null, []],
            'endpoint with CR/LF injection' => ['POST', "/synthetic\r\nX-Injected: 1", null, []],
            'empty header name' => ['POST', '/synthetic', null, ['' => 'value']],
            'non-string header value' => ['POST', '/synthetic', null, ['X-Custom' => 123]],
            'header value with CR/LF injection' => ['POST', '/synthetic', null, ["X-Custom" => "value\r\nX-Injected: 1"]],
            'nested array query value' => ['POST', '/synthetic', ['filter' => ['a', 'b']], []],
        ];
    }

    public function testConstructorRejectsCaseInsensitiveDuplicateHeaderNames(): void
    {
        $this->expectException(ArgumentException::class);

        new AccessWorldpayRequest('POST', '/synthetic', null, null, [
            'X-Custom-Header' => 'first',
            'x-custom-header' => 'second',
        ]);
    }

    public function testValidateIsIdempotentForAnUnchangedValidRequest(): void
    {
        $request = new AccessWorldpayRequest('POST', '/synthetic', '{}');

        // Should not throw when called again with no mutation.
        $request->validate();
        $request->validate();

        $this->addToAssertionCount(1);
    }

    public function testValidateDetectsInvalidMutationAfterConstruction(): void
    {
        $request = new AccessWorldpayRequest('POST', '/synthetic', '{}');

        $request->endpoint = 'synthetic-without-leading-slash';

        $this->expectException(ArgumentException::class);

        $request->validate();
    }

    public function testMaskingMetadataIsIndependentBetweenTwoInstances(): void
    {
        $first = new AccessWorldpayRequest('POST', '/synthetic', '{}');
        $first->addMaskingRule('secret', 'synthetic-secret-value');

        $second = new AccessWorldpayRequest('POST', '/synthetic', '{}');

        $this->assertArrayHasKey('secret', $first->getMaskedValues());
        $this->assertArrayNotHasKey('secret', $second->getMaskedValues());
    }

    public function testSensitiveHeadersAreRedactedInDiagnosticsWithoutMutatingOriginalHeaders(): void
    {
        $request = new AccessWorldpayRequest('POST', '/synthetic', null, null, [
            'Authorization' => 'Basic synthetic-credential-value',
            'X-Plain-Header' => 'visible-value',
        ]);

        $maskedHeaders = $request->getMaskedHeaders();

        $this->assertSame('[REDACTED]', $maskedHeaders['Authorization']);
        $this->assertSame('visible-value', $maskedHeaders['X-Plain-Header']);
        $this->assertSame('Basic synthetic-credential-value', $request->headers['Authorization']);
    }
}

