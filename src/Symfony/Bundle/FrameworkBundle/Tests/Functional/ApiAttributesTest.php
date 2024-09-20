<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Composer\InstalledVersions;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Validator\Constraints as Assert;

class ApiAttributesTest extends AbstractWebTestCase
{
    /**
     * @dataProvider mapQueryStringProvider
     */
    public function testMapQueryString(string $uri, array $query, string $expectedResponse, int $expectedStatusCode)
    {
        $client = self::createClient(['test_case' => 'ApiAttributesTest']);

        $client->request('GET', $uri, $query);

        $response = $client->getResponse();

        if ($expectedResponse) {
            self::assertJsonStringEqualsJsonString($expectedResponse, $response->getContent());
        } else {
            self::assertEmpty($response->getContent());
        }
        self::assertSame($expectedStatusCode, $response->getStatusCode());
    }

    public static function mapQueryStringProvider(): iterable
    {
        yield 'empty query string mapping nullable attribute' => [
            'uri' => '/map-query-string-to-nullable-attribute.json',
            'query' => [],
            'expectedResponse' => '',
            'expectedStatusCode' => 204,
        ];

        yield 'valid query string mapping nullable attribute' => [
            'uri' => '/map-query-string-to-nullable-attribute.json',
            'query' => ['filter' => ['status' => 'approved', 'quantity' => '4']],
            'expectedResponse' => <<<'JSON'
                {
                    "filter": {
                        "status": "approved",
                        "quantity": 4
                    }
                }
                JSON,
            'expectedStatusCode' => 200,
        ];

        yield 'invalid query string mapping nullable attribute' => [
            'uri' => '/map-query-string-to-nullable-attribute.json',
            'query' => ['filter' => ['status' => 'approved', 'quantity' => '200']],
            'expectedResponse' => <<<'JSON'
                {
                    "type": "https:\/\/symfony.com\/errors\/validation",
                    "title": "Validation Failed",
                    "status": 404,
                    "detail": "filter.quantity: This value should be less than 10.",
                    "violations": [
                        {
                            "propertyPath": "filter.quantity",
                            "title": "This value should be less than 10.",
                            "template": "This value should be less than {{ compared_value }}.",
                            "parameters": {
                                "{{ value }}": "200",
                                "{{ compared_value }}": "10",
                                "{{ compared_value_type }}": "int"
                            },
                            "type": "urn:uuid:079d7420-2d13-460c-8756-de810eeb37d2"
                        }
                    ]
                }
                JSON,
            'expectedStatusCode' => 404,
        ];

        yield 'empty query string mapping attribute with default value' => [
            'uri' => '/map-query-string-to-attribute-with-default-value.json',
            'query' => [],
            'expectedResponse' => <<<'JSON'
                {
                    "filter": {
                        "status": "approved",
                        "quantity": 5
                    }
                }
                JSON,
            'expectedStatusCode' => 200,
        ];

        yield 'valid query string mapping attribute with default value' => [
            'uri' => '/map-query-string-to-attribute-with-default-value.json',
            'query' => ['filter' => ['status' => 'approved', 'quantity' => '4']],
            'expectedResponse' => <<<'JSON'
                {
                    "filter": {
                        "status": "approved",
                        "quantity": 4
                    }
                }
                JSON,
            'expectedStatusCode' => 200,
        ];

        yield 'invalid query string mapping attribute with default value' => [
            'uri' => '/map-query-string-to-attribute-with-default-value.json',
            'query' => ['filter' => ['status' => 'approved', 'quantity' => '200']],
            'expectedResponse' => <<<'JSON'
                {
                    "type": "https:\/\/symfony.com\/errors\/validation",
                    "title": "Validation Failed",
                    "status": 404,
                    "detail": "filter.quantity: This value should be less than 10.",
                    "violations": [
                        {
                            "propertyPath": "filter.quantity",
                            "title": "This value should be less than 10.",
                            "template": "This value should be less than {{ compared_value }}.",
                            "parameters": {
                                "{{ value }}": "200",
                                "{{ compared_value }}": "10",
                                "{{ compared_value_type }}": "int"
                            },
                            "type": "urn:uuid:079d7420-2d13-460c-8756-de810eeb37d2"
                        }
                    ]
                }
                JSON,
            'expectedStatusCode' => 404,
        ];

        $expectedResponse = <<<'JSON'
                {
                    "type": "https:\/\/symfony.com\/errors\/validation",
                    "title": "Validation Failed",
                    "status": 404,
                    "detail": "filter: This value should be of type Symfony\\Bundle\\FrameworkBundle\\Tests\\Functional\\Filter.",
                    "violations": [
                        {
                            "parameters": {
                                "hint": "Failed to create object because the class misses the \"filter\" property.",
                                "{{ type }}": "Symfony\\Bundle\\FrameworkBundle\\Tests\\Functional\\Filter"
                            },
                            "propertyPath": "filter",
                            "template": "This value should be of type {{ type }}.",
                            "title": "This value should be of type Symfony\\Bundle\\FrameworkBundle\\Tests\\Functional\\Filter."
                        }
                    ]
                }
                JSON;

        $httpKernelVersion = InstalledVersions::getVersion('symfony/http-kernel');
        if ($httpKernelVersion && version_compare($httpKernelVersion, '7.2.0', '<')) {
            $expectedResponse = <<<'JSON'
                {
                    "type": "https:\/\/tools.ietf.org\/html\/rfc2616#section-10",
                    "title": "An error occurred",
                    "status": 404,
                    "detail": "Not Found"
                }
                JSON;
        }

        yield 'empty query string mapping non-nullable attribute without default value' => [
            'uri' => '/map-query-string-to-non-nullable-attribute-without-default-value.json',
            'query' => [],
            'expectedResponse' => $expectedResponse,
            'expectedStatusCode' => 404,
        ];

        yield 'valid query string mapping non-nullable attribute without default value' => [
            'uri' => '/map-query-string-to-non-nullable-attribute-without-default-value.json',
            'query' => ['filter' => ['status' => 'approved', 'quantity' => '4']],
            'expectedResponse' => <<<'JSON'
                {
                    "filter": {
                        "status": "approved",
                        "quantity": 4
                    }
                }
                JSON,
            'expectedStatusCode' => 200,
        ];

        yield 'invalid query string mapping non-nullable attribute without default value' => [
            'uri' => '/map-query-string-to-non-nullable-attribute-without-default-value.json',
            'query' => ['filter' => ['status' => 'approved', 'quantity' => '11']],
            'expectedResponse' => <<<'JSON'
                {
                    "type": "https:\/\/symfony.com\/errors\/validation",
                    "title": "Validation Failed",
                    "status": 404,
                    "detail": "filter.quantity: This value should be less than 10.",
                    "violations": [
                        {
                            "propertyPath": "filter.quantity",
                            "title": "This value should be less than 10.",
                            "template": "This value should be less than {{ compared_value }}.",
                            "parameters": {
                                "{{ value }}": "11",
                                "{{ compared_value }}": "10",
                                "{{ compared_value_type }}": "int"
                            },
                            "type": "urn:uuid:079d7420-2d13-460c-8756-de810eeb37d2"
                        }
                    ]
                }
                JSON,
            'expectedStatusCode' => 404,
        ];
    }

    /**
     * @dataProvider mapRequestPayloadProvider
     */
    public function testMapRequestPayload(string $uri, string $format, array $parameters, ?string $content, string $expectedResponse, int $expectedStatusCode)
    {
        $client = self::createClient(['test_case' => 'ApiAttributesTest']);

        [$acceptHeader, $assertion] = [
            'html' => ['text/html', self::assertStringContainsString(...)],
            'json' => ['application/json', self::assertJsonStringEqualsJsonString(...)],
            'xml' => ['text/xml', self::assertXmlStringEqualsXmlString(...)],
            'dummy' => ['application/dummy', self::assertStringContainsString(...)],
        ][$format];

        $client->request(
            'POST',
            $uri,
            $parameters,
            [],
            ['HTTP_ACCEPT' => $acceptHeader, 'CONTENT_TYPE' => $acceptHeader],
            $content
        );

        $response = $client->getResponse();
        $responseContent = $response->getContent();

        if ($expectedResponse) {
            $assertion($expectedResponse, $responseContent);
        } else {
            self::assertSame('', $responseContent);
        }

        self::assertSame($expectedStatusCode, $response->getStatusCode());
    }

    public static function mapRequestPayloadProvider(): iterable
    {
        yield 'empty request mapping nullable attribute' => [
            'uri' => '/map-request-to-nullable-attribute.json',
            'format' => 'json',
            'parameters' => [],
            'content' => '',
            'expectedResponse' => '',
            'expectedStatusCode' => 204,
        ];

        yield 'valid request with json content mapping nullable attribute' => [
            'uri' => '/map-request-to-nullable-attribute.json',
            'format' => 'json',
            'parameters' => [],
            'content' => <<<'JSON'
                {
                    "comment": "Hello everyone!",
                    "approved": false
                }
                JSON,
            'expectedResponse' => <<<'JSON'
                {
                    "comment": "Hello everyone!",
                    "approved": false
                }
                JSON,
            'expectedStatusCode' => 200,
        ];

        yield 'valid request with xml content mapping nullable attribute' => [
            'uri' => '/map-request-to-nullable-attribute.xml',
            'format' => 'xml',
            'parameters' => [],
            'content' => <<<'XML'
                <request>
                    <comment>Hello everyone!</comment>
                    <approved>true</approved>
                </request>
                XML,
            'expectedResponse' => <<<'XML'
                <response>
                    <comment>Hello everyone!</comment>
                    <approved>1</approved>
                </response>
                XML,
            'expectedStatusCode' => 200,
        ];

        yield 'valid request mapping nullable attribute' => [
            'uri' => '/map-request-to-nullable-attribute.json',
            'format' => 'json',
            'parameters' => ['comment' => 'Hello everyone!', 'approved' => '0'],
            'content' => null,
            'expectedResponse' => <<<'JSON'
                {
                    "comment": "Hello everyone!",
                    "approved": false
                }
                JSON,
            'expectedStatusCode' => 200,
        ];

        yield 'malformed json request mapping nullable attribute' => [
            'uri' => '/map-request-to-nullable-attribute.json',
            'format' => 'json',
            'parameters' => [],
            'content' => <<<'JSON'
                {
                    "comment": "Hello everyone!",
                    "approved": false,
                }
                JSON,
            'expectedResponse' => <<<'JSON'
                {
                    "type": "https:\/\/tools.ietf.org\/html\/rfc2616#section-10",
                    "title": "An error occurred",
                    "status": 400,
                    "detail": "Bad Request"
                }
                JSON,
            'expectedStatusCode' => 400,
        ];

        yield 'request with unsupported format mapping nullable attribute' => [
            'uri' => '/map-request-to-nullable-attribute.dummy',
            'format' => 'dummy',
            'parameters' => [],
            'content' => 'Hello',
            'expectedResponse' => '415 Unsupported Media Type',
            'expectedStatusCode' => 415,
        ];

        yield 'request with invalid type mapping nullable attribute' => [
            'uri' => '/map-request-to-nullable-attribute.json',
            'format' => 'json',
            'parameters' => [],
            'content' => <<<'JSON'
                {
                    "comment": "Hello everyone!",
                    "approved": "string instead of bool"
                }
                JSON,
            'expectedResponse' => <<<'JSON'
                {
                    "type": "https:\/\/symfony.com\/errors\/validation",
                    "title": "Validation Failed",
                    "status": 422,
                    "detail": "approved: This value should be of type bool.",
                    "violations": [
                        {
                            "propertyPath": "approved",
                            "title": "This value should be of type bool.",
                            "template": "This value should be of type {{ type }}.",
                            "parameters": {
                                "{{ type }}": "bool"
                            }
                        }
                    ]
                }
                JSON,
            'expectedStatusCode' => 422,
        ];

        yield 'invalid request with json content mapping nullable attribute' => [
            'uri' => '/map-request-to-nullable-attribute.json',
            'format' => 'json',
            'parameters' => [],
            'content' => <<<'JSON'
                {
                    "comment": "",
                    "approved": true
                }
                JSON,
            'expectedResponse' => <<<'JSON'
                {
                    "type": "https:\/\/symfony.com\/errors\/validation",
                    "title": "Validation Failed",
                    "status": 422,
                    "detail": "comment: This value should not be blank.\ncomment: This value is too short. It should have 10 characters or more.",
                    "violations": [
                        {
                            "propertyPath": "comment",
                            "title": "This value should not be blank.",
                            "template": "This value should not be blank.",
                            "parameters": {
                                "{{ value }}": "\"\""
                            },
                            "type": "urn:uuid:c1051bb4-d103-4f74-8988-acbcafc7fdc3"
                        },
                        {
                            "propertyPath": "comment",
                            "title": "This value is too short. It should have 10 characters or more.",
                            "template": "This value is too short. It should have {{ limit }} character or more.|This value is too short. It should have {{ limit }} characters or more.",
                            "parameters": {
                                "{{ value }}": "\"\"",
                                "{{ limit }}": "10",
                                "{{ value_length }}": "0"
                            },
                            "type": "urn:uuid:9ff3fdc4-b214-49db-8718-39c315e33d45"
                        }
                    ]
                }
                JSON,
            'expectedStatusCode' => 422,
        ];

        yield 'invalid request with xml content mapping nullable attribute' => [
            'uri' => '/map-request-to-nullable-attribute.xml',
            'format' => 'xml',
            'parameters' => [],
            'content' => <<<'XML'
                <request>
                    <comment>H</comment>
                    <approved>false</approved>
                </request>
                XML,
            'expectedResponse' => <<<'XML'
                <?xml version="1.0"?>
                <response>
                    <type>https://symfony.com/errors/validation</type>
                    <title>Validation Failed</title>
                    <status>422</status>
                    <detail>comment: This value is too short. It should have 10 characters or more.</detail>
                    <violations>
                        <propertyPath>comment</propertyPath>
                        <title>This value is too short. It should have 10 characters or more.</title>
                        <template>This value is too short. It should have {{ limit }} character or more.|This value is too short. It should have {{ limit }} characters or more.</template>
                        <parameters>
                            <item key="{{ value }}">"H"</item>
                            <item key="{{ limit }}">10</item>
                            <item key="{{ value_length }}">1</item>
                        </parameters>
                        <type>urn:uuid:9ff3fdc4-b214-49db-8718-39c315e33d45</type>
                    </violations>
                </response>
                XML,
            'expectedStatusCode' => 422,
        ];

        yield 'invalid request mapping nullable attribute' => [
            'uri' => '/map-request-to-nullable-attribute.json',
            'format' => 'json',
            'parameters' => ['comment' => '', 'approved' => '1'],
            'content' => null,
            'expectedResponse' => <<<'JSON'
                {
                    "type": "https:\/\/symfony.com\/errors\/validation",
                    "title": "Validation Failed",
                    "status": 422,
                    "detail": "comment: This value should not be blank.\ncomment: This value is too short. It should have 10 characters or more.",
                    "violations": [
                        {
                            "propertyPath": "comment",
                            "title": "This value should not be blank.",
                            "template": "This value should not be blank.",
                            "parameters": {
                                "{{ value }}": "\"\""
                            },
                            "type": "urn:uuid:c1051bb4-d103-4f74-8988-acbcafc7fdc3"
                        },
                        {
                            "propertyPath": "comment",
                            "title": "This value is too short. It should have 10 characters or more.",
                            "template": "This value is too short. It should have {{ limit }} character or more.|This value is too short. It should have {{ limit }} characters or more.",
                            "parameters": {
                                "{{ value }}": "\"\"",
                                "{{ limit }}": "10",
                                "{{ value_length }}": "0"
                            },
                            "type": "urn:uuid:9ff3fdc4-b214-49db-8718-39c315e33d45"
                        }
                    ]
                }
                JSON,
            'expectedStatusCode' => 422,
        ];

        yield 'empty request mapping attribute with default value' => [
            'uri' => '/map-request-to-attribute-with-default-value.json',
            'format' => 'json',
            'parameters' => [],
            'content' => '',
            'expectedResponse' => <<<'JSON'
                {
                    "comment": "Hello everyone!",
                    "approved": false
                }
                JSON,
            'expectedStatusCode' => 200,
        ];

        yield 'valid request with json content mapping attribute with default value' => [
            'uri' => '/map-request-to-attribute-with-default-value.json',
            'format' => 'json',
            'parameters' => [],
            'content' => <<<'JSON'
                {
                    "comment": "Hello everyone!",
                    "approved": false
                }
                JSON,
            'expectedResponse' => <<<'JSON'
                {
                    "comment": "Hello everyone!",
                    "approved": false
                }
                JSON,
            'expectedStatusCode' => 200,
        ];

        yield 'valid request with xml content mapping attribute with default value' => [
            'uri' => '/map-request-to-attribute-with-default-value.xml',
            'format' => 'xml',
            'parameters' => [],
            'content' => <<<'XML'
                <request>
                    <comment>Hello everyone!</comment>
                    <approved>true</approved>
                </request>
                XML,
            'expectedResponse' => <<<'XML'
                <response>
                    <comment>Hello everyone!</comment>
                    <approved>1</approved>
                </response>
                XML,
            'expectedStatusCode' => 200,
        ];

        yield 'valid request mapping attribute with default value' => [
            'uri' => '/map-request-to-attribute-with-default-value.json',
            'format' => 'json',
            'parameters' => ['comment' => 'Hello everyone!', 'approved' => '0'],
            'content' => null,
            'expectedResponse' => <<<'JSON'
                {
                    "comment": "Hello everyone!",
                    "approved": false
                }
                JSON,
            'expectedStatusCode' => 200,
        ];

        yield 'malformed json request mapping attribute with default value' => [
            'uri' => '/map-request-to-attribute-with-default-value.json',
            'format' => 'json',
            'parameters' => [],
            'content' => <<<'JSON'
                {
                    "comment": "Hello everyone!",
                    "approved": false,
                }
                JSON,
            'expectedResponse' => <<<'JSON'
                {
                    "type": "https:\/\/tools.ietf.org\/html\/rfc2616#section-10",
                    "title": "An error occurred",
                    "status": 400,
                    "detail": "Bad Request"
                }
                JSON,
            'expectedStatusCode' => 400,
        ];

        yield 'request with unsupported format mapping attribute with default value' => [
            'uri' => '/map-request-to-attribute-with-default-value.dummy',
            'format' => 'dummy',
            'parameters' => [],
            'content' => 'Hello',
            'expectedResponse' => '415 Unsupported Media Type',
            'expectedStatusCode' => 415,
        ];

        yield 'request with invalid type mapping attribute with default value' => [
            'uri' => '/map-request-to-attribute-with-default-value.json',
            'format' => 'json',
            'parameters' => [],
            'content' => <<<'JSON'
                {
                    "comment": "Hello everyone!",
                    "approved": "string instead of bool"
                }
                JSON,
            'expectedResponse' => <<<'JSON'
                {
                    "type": "https:\/\/symfony.com\/errors\/validation",
                    "title": "Validation Failed",
                    "status": 422,
                    "detail": "approved: This value should be of type bool.",
                    "violations": [
                        {
                            "propertyPath": "approved",
                            "title": "This value should be of type bool.",
                            "template": "This value should be of type {{ type }}.",
                            "parameters": {
                                "{{ type }}": "bool"
                            }
                        }
                    ]
                }
                JSON,
            'expectedStatusCode' => 422,
        ];

        yield 'invalid request with json content mapping attribute with default value' => [
            'uri' => '/map-request-to-attribute-with-default-value.json',
            'format' => 'json',
            'parameters' => [],
            'content' => <<<'JSON'
                {
                    "comment": "",
                    "approved": true
                }
                JSON,
            'expectedResponse' => <<<'JSON'
                {
                    "type": "https:\/\/symfony.com\/errors\/validation",
                    "title": "Validation Failed",
                    "status": 422,
                    "detail": "comment: This value should not be blank.\ncomment: This value is too short. It should have 10 characters or more.",
                    "violations": [
                        {
                            "propertyPath": "comment",
                            "title": "This value should not be blank.",
                            "template": "This value should not be blank.",
                            "parameters": {
                                "{{ value }}": "\"\""
                            },
                            "type": "urn:uuid:c1051bb4-d103-4f74-8988-acbcafc7fdc3"
                        },
                        {
                            "propertyPath": "comment",
                            "title": "This value is too short. It should have 10 characters or more.",
                            "template": "This value is too short. It should have {{ limit }} character or more.|This value is too short. It should have {{ limit }} characters or more.",
                            "parameters": {
                                "{{ value }}": "\"\"",
                                "{{ limit }}": "10",
                                "{{ value_length }}": "0"
                            },
                            "type": "urn:uuid:9ff3fdc4-b214-49db-8718-39c315e33d45"
                        }
                    ]
                }
                JSON,
            'expectedStatusCode' => 422,
        ];

        yield 'invalid request with xml content mapping attribute with default value' => [
            'uri' => '/map-request-to-attribute-with-default-value.xml',
            'format' => 'xml',
            'parameters' => [],
            'content' => <<<'XML'
                <request>
                    <comment>H</comment>
                    <approved>false</approved>
                </request>
                XML,
            'expectedResponse' => <<<'XML'
                <?xml version="1.0"?>
                <response>
                    <type>https://symfony.com/errors/validation</type>
                    <title>Validation Failed</title>
                    <status>422</status>
                    <detail>comment: This value is too short. It should have 10 characters or more.</detail>
                    <violations>
                        <propertyPath>comment</propertyPath>
                        <title>This value is too short. It should have 10 characters or more.</title>
                        <template>This value is too short. It should have {{ limit }} character or more.|This value is too short. It should have {{ limit }} characters or more.</template>
                        <parameters>
                            <item key="{{ value }}">"H"</item>
                            <item key="{{ limit }}">10</item>
                            <item key="{{ value_length }}">1</item>
                        </parameters>
                        <type>urn:uuid:9ff3fdc4-b214-49db-8718-39c315e33d45</type>
                    </violations>
                </response>
                XML,
            'expectedStatusCode' => 422,
        ];

        yield 'invalid request mapping attribute with default value' => [
            'uri' => '/map-request-to-attribute-with-default-value.json',
            'format' => 'json',
            'input' => ['comment' => '', 'approved' => '1'],
            'content' => null,
            'expectedResponse' => <<<'JSON'
                {
                    "type": "https:\/\/symfony.com\/errors\/validation",
                    "title": "Validation Failed",
                    "status": 422,
                    "detail": "comment: This value should not be blank.\ncomment: This value is too short. It should have 10 characters or more.",
                    "violations": [
                        {
                            "propertyPath": "comment",
                            "title": "This value should not be blank.",
                            "template": "This value should not be blank.",
                            "parameters": {
                                "{{ value }}": "\"\""
                            },
                            "type": "urn:uuid:c1051bb4-d103-4f74-8988-acbcafc7fdc3"
                        },
                        {
                            "propertyPath": "comment",
                            "title": "This value is too short. It should have 10 characters or more.",
                            "template": "This value is too short. It should have {{ limit }} character or more.|This value is too short. It should have {{ limit }} characters or more.",
                            "parameters": {
                                "{{ value }}": "\"\"",
                                "{{ limit }}": "10",
                                "{{ value_length }}": "0"
                            },
                            "type": "urn:uuid:9ff3fdc4-b214-49db-8718-39c315e33d45"
                        }
                    ]
                }
                JSON,
            'expectedStatusCode' => 422,
        ];

        $expectedStatusCode = 400;
        $expectedResponse = <<<'JSON'
                {
                  "type":"https:\/\/tools.ietf.org\/html\/rfc2616#section-10",
                  "title":"An error occurred",
                  "status":400,
                  "detail":"Bad Request"
                }
                JSON;

        $httpKernelVersion = InstalledVersions::getVersion('symfony/http-kernel');
        if ($httpKernelVersion && version_compare($httpKernelVersion, '7.2.0', '<')) {
            $expectedStatusCode = 422;
            $expectedResponse = <<<'JSON'
                {
                    "type": "https:\/\/tools.ietf.org\/html\/rfc2616#section-10",
                    "title": "An error occurred",
                    "status": 422,
                    "detail": "Unprocessable Content"
                }
                JSON;
        }

        yield 'empty request mapping non-nullable attribute without default value' => [
            'uri' => '/map-request-to-non-nullable-attribute-without-default-value.json',
            'format' => 'json',
            'parameters' => [],
            'content' => '',
            'expectedResponse' => $expectedResponse,
            'expectedStatusCode' => $expectedStatusCode,
        ];

        yield 'valid request with json content mapping non-nullable attribute without default value' => [
            'uri' => '/map-request-to-non-nullable-attribute-without-default-value.json',
            'format' => 'json',
            'parameters' => [],
            'content' => <<<'JSON'
                {
                    "comment": "Hello everyone!",
                    "approved": false
                }
                JSON,
            'expectedResponse' => <<<'JSON'
                {
                    "comment": "Hello everyone!",
                    "approved": false
                }
                JSON,
            'expectedStatusCode' => 200,
        ];

        yield 'valid request with xml content mapping non-nullable attribute without default value' => [
            'uri' => '/map-request-to-non-nullable-attribute-without-default-value.xml',
            'format' => 'xml',
            'parameters' => [],
            'content' => <<<'XML'
                <request>
                    <comment>Hello everyone!</comment>
                    <approved>true</approved>
                </request>
                XML,
            'expectedResponse' => <<<'XML'
                <response>
                    <comment>Hello everyone!</comment>
                    <approved>1</approved>
                </response>
                XML,
            'expectedStatusCode' => 200,
        ];

        yield 'valid request mapping non-nullable attribute without default value' => [
            'uri' => '/map-request-to-non-nullable-attribute-without-default-value.json',
            'format' => 'json',
            'parameters' => ['comment' => 'Hello everyone!', 'approved' => '0'],
            'content' => null,
            'expectedResponse' => <<<'JSON'
                {
                    "comment": "Hello everyone!",
                    "approved": false
                }
                JSON,
            'expectedStatusCode' => 200,
        ];

        yield 'malformed json request mapping non-nullable attribute without default value' => [
            'uri' => '/map-request-to-non-nullable-attribute-without-default-value.json',
            'format' => 'json',
            'parameters' => [],
            'content' => <<<'JSON'
                {
                    "comment": "Hello everyone!",
                    "approved": false,
                }
                JSON,
            'expectedResponse' => <<<'JSON'
                {
                    "type": "https:\/\/tools.ietf.org\/html\/rfc2616#section-10",
                    "title": "An error occurred",
                    "status": 400,
                    "detail": "Bad Request"
                }
                JSON,
            'expectedStatusCode' => 400,
        ];

        yield 'request with unsupported format mapping non-nullable attribute without default value' => [
            'uri' => '/map-request-to-non-nullable-attribute-without-default-value.dummy',
            'format' => 'dummy',
            'parameters' => [],
            'content' => 'Hello',
            'expectedResponse' => '415 Unsupported Media Type',
            'expectedStatusCode' => 415,
        ];

        yield 'request with invalid type mapping non-nullable attribute without default value' => [
            'uri' => '/map-request-to-non-nullable-attribute-without-default-value.json',
            'format' => 'json',
            'parameters' => [],
            'content' => <<<'JSON'
                {
                    "comment": "Hello everyone!",
                    "approved": "string instead of bool"
                }
                JSON,
            'expectedResponse' => <<<'JSON'
                {
                    "type": "https:\/\/symfony.com\/errors\/validation",
                    "title": "Validation Failed",
                    "status": 422,
                    "detail": "approved: This value should be of type bool.",
                    "violations": [
                        {
                            "propertyPath": "approved",
                            "title": "This value should be of type bool.",
                            "template": "This value should be of type {{ type }}.",
                            "parameters": {
                                "{{ type }}": "bool"
                            }
                        }
                    ]
                }
                JSON,
            'expectedStatusCode' => 422,
        ];

        yield 'invalid request with json content mapping non-nullable attribute without default value' => [
            'uri' => '/map-request-to-non-nullable-attribute-without-default-value.json',
            'format' => 'json',
            'parameters' => [],
            'content' => <<<'JSON'
                {
                    "comment": "",
                    "approved": true
                }
                JSON,
            'expectedResponse' => <<<'JSON'
                {
                    "type": "https:\/\/symfony.com\/errors\/validation",
                    "title": "Validation Failed",
                    "status": 422,
                    "detail": "comment: This value should not be blank.\ncomment: This value is too short. It should have 10 characters or more.",
                    "violations": [
                        {
                            "propertyPath": "comment",
                            "title": "This value should not be blank.",
                            "template": "This value should not be blank.",
                            "parameters": {
                                "{{ value }}": "\"\""
                            },
                            "type": "urn:uuid:c1051bb4-d103-4f74-8988-acbcafc7fdc3"
                        },
                        {
                            "propertyPath": "comment",
                            "title": "This value is too short. It should have 10 characters or more.",
                            "template": "This value is too short. It should have {{ limit }} character or more.|This value is too short. It should have {{ limit }} characters or more.",
                            "parameters": {
                                "{{ value }}": "\"\"",
                                "{{ limit }}": "10",
                                "{{ value_length }}": "0"
                            },
                            "type": "urn:uuid:9ff3fdc4-b214-49db-8718-39c315e33d45"
                        }
                    ]
                }
                JSON,
            'expectedStatusCode' => 422,
        ];

        yield 'invalid request with xml content mapping non-nullable attribute without default value' => [
            'uri' => '/map-request-to-non-nullable-attribute-without-default-value.xml',
            'format' => 'xml',
            'parameters' => [],
            'content' => <<<'XML'
                <request>
                    <comment>H</comment>
                    <approved>false</approved>
                </request>
                XML,
            'expectedResponse' => <<<'XML'
                <?xml version="1.0"?>
                <response>
                    <type>https://symfony.com/errors/validation</type>
                    <title>Validation Failed</title>
                    <status>422</status>
                    <detail>comment: This value is too short. It should have 10 characters or more.</detail>
                    <violations>
                        <propertyPath>comment</propertyPath>
                        <title>This value is too short. It should have 10 characters or more.</title>
                        <template>This value is too short. It should have {{ limit }} character or more.|This value is too short. It should have {{ limit }} characters or more.</template>
                        <parameters>
                            <item key="{{ value }}">"H"</item>
                            <item key="{{ limit }}">10</item>
                            <item key="{{ value_length }}">1</item>
                        </parameters>
                        <type>urn:uuid:9ff3fdc4-b214-49db-8718-39c315e33d45</type>
                    </violations>
                </response>
                XML,
            'expectedStatusCode' => 422,
        ];

        yield 'invalid request mapping non-nullable attribute without default value' => [
            'uri' => '/map-request-to-non-nullable-attribute-without-default-value.json',
            'format' => 'json',
            'input' => ['comment' => '', 'approved' => '1'],
            'content' => null,
            'expectedResponse' => <<<'JSON'
                {
                    "type": "https:\/\/symfony.com\/errors\/validation",
                    "title": "Validation Failed",
                    "status": 422,
                    "detail": "comment: This value should not be blank.\ncomment: This value is too short. It should have 10 characters or more.",
                    "violations": [
                        {
                            "propertyPath": "comment",
                            "title": "This value should not be blank.",
                            "template": "This value should not be blank.",
                            "parameters": {
                                "{{ value }}": "\"\""
                            },
                            "type": "urn:uuid:c1051bb4-d103-4f74-8988-acbcafc7fdc3"
                        },
                        {
                            "propertyPath": "comment",
                            "title": "This value is too short. It should have 10 characters or more.",
                            "template": "This value is too short. It should have {{ limit }} character or more.|This value is too short. It should have {{ limit }} characters or more.",
                            "parameters": {
                                "{{ value }}": "\"\"",
                                "{{ limit }}": "10",
                                "{{ value_length }}": "0"
                            },
                            "type": "urn:uuid:9ff3fdc4-b214-49db-8718-39c315e33d45"
                        }
                    ]
                }
                JSON,
            'expectedStatusCode' => 422,
        ];
    }
}

class WithMapQueryStringToNullableAttributeController
{
    public function __invoke(#[MapQueryString] ?QueryString $query): Response
    {
        if (!$query) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(
            ['filter' => ['status' => $query->filter->status, 'quantity' => $query->filter->quantity]],
        );
    }
}

class WithMapQueryStringToAttributeWithDefaultValueController
{
    public function __invoke(#[MapQueryString] QueryString $query = new QueryString(new Filter('approved', 5))): Response
    {
        return new JsonResponse(
            ['filter' => ['status' => $query->filter->status, 'quantity' => $query->filter->quantity]],
        );
    }
}

class WithMapQueryStringToNonNullableAttributeWithoutDefaultValueController
{
    public function __invoke(#[MapQueryString] QueryString $query): Response
    {
        return new JsonResponse(
            ['filter' => ['status' => $query->filter->status, 'quantity' => $query->filter->quantity]],
        );
    }
}

class WithMapRequestToNullableAttributeController
{
    public function __invoke(#[MapRequestPayload] ?RequestBody $body, Request $request): Response
    {
        if ('json' === $request->getPreferredFormat('json')) {
            if (!$body) {
                return new Response('', Response::HTTP_NO_CONTENT);
            }

            return new JsonResponse(['comment' => $body->comment, 'approved' => $body->approved]);
        }

        return new Response(
            <<<XML
            <response>
                <comment>{$body->comment}</comment>
                <approved>{$body->approved}</approved>
            </response>
            XML
        );
    }
}

class WithMapRequestToAttributeWithDefaultValueController
{
    public function __invoke(Request $request, #[MapRequestPayload] RequestBody $body = new RequestBody('Hello everyone!', false)): Response
    {
        if ('json' === $request->getPreferredFormat('json')) {
            return new JsonResponse(['comment' => $body->comment, 'approved' => $body->approved]);
        }

        return new Response(
            <<<XML
            <response>
                <comment>{$body->comment}</comment>
                <approved>{$body->approved}</approved>
            </response>
            XML
        );
    }
}

class WithMapRequestToNonNullableAttributeWithoutDefaultValueController
{
    public function __invoke(Request $request, #[MapRequestPayload] RequestBody $body): Response
    {
        if ('json' === $request->getPreferredFormat('json')) {
            return new JsonResponse(['comment' => $body->comment, 'approved' => $body->approved]);
        }

        return new Response(
            <<<XML
            <response>
                <comment>{$body->comment}</comment>
                <approved>{$body->approved}</approved>
            </response>
            XML
        );
    }
}

class QueryString
{
    public function __construct(
        #[Assert\Valid]
        public readonly Filter $filter,
    ) {
    }
}

class Filter
{
    public function __construct(
        public readonly string $status,
        #[Assert\LessThan(10)]
        public readonly int $quantity,
    ) {
    }
}

class RequestBody
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 10)]
        public readonly string $comment,
        public readonly bool $approved,
    ) {
    }
}
