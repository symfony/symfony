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
    public function testMapQueryString(array $query, string $expectedResponse, int $expectedStatusCode)
    {
        $client = self::createClient(['test_case' => 'ApiAttributesTest']);

        $client->request('GET', '/map-query-string.json', $query);

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
        yield 'empty' => [
            'query' => [],
            'expectedResponse' => '',
            'expectedStatusCode' => 204,
        ];

        yield 'valid' => [
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

        yield 'invalid' => [
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
    }

    /**
     * @dataProvider mapRequestPayloadProvider
     */
    public function testMapRequestPayload(string $format, array $parameters, ?string $content, string $expectedResponse, int $expectedStatusCode)
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
            '/map-request-body.'.$format,
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
        yield 'empty' => [
            'format' => 'json',
            'parameters' => [],
            'content' => '',
            'expectedResponse' => '',
            'expectedStatusCode' => 204,
        ];

        yield 'valid json' => [
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

        yield 'malformed json' => [
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

        yield 'unsupported format' => [
            'format' => 'dummy',
            'parameters' => [],
            'content' => 'Hello',
            'expectedResponse' => '415 Unsupported Media Type',
            'expectedStatusCode' => 415,
        ];

        yield 'valid xml' => [
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

        yield 'invalid type' => [
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

        yield 'validation error json' => [
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

        yield 'validation error xml' => [
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

        yield 'valid input' => [
            'format' => 'json',
            'input' => ['comment' => 'Hello everyone!', 'approved' => '0'],
            'content' => null,
            'expectedResponse' => <<<'JSON'
                {
                    "comment": "Hello everyone!",
                    "approved": false
                }
                JSON,
            'expectedStatusCode' => 200,
        ];

        yield 'validation error input' => [
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

class WithMapQueryStringController
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

class WithMapRequestPayloadController
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
