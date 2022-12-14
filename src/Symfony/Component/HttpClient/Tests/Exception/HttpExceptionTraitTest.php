<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\HttpExceptionTrait;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class HttpExceptionTraitTest extends TestCase
{
    public static function provideParseError(): iterable
    {
        $errorWithoutMessage = 'HTTP/1.1 400 Bad Request returned for "http://example.com".';

        $errorWithMessage = <<<ERROR
An error occurred

Some details
ERROR;

        yield ['application/ld+json', '{"hydra:title": "An error occurred", "hydra:description": "Some details"}', $errorWithMessage];
        yield ['application/problem+json', '{"title": "An error occurred", "detail": "Some details"}', $errorWithMessage];
        yield ['application/vnd.api+json', '{"title": "An error occurred", "detail": "Some details"}', $errorWithMessage];
        yield ['application/json', '{"title": "An error occurred", "detail": {"field_name": ["Some details"]}}', $errorWithoutMessage];
    }

    /**
     * @dataProvider provideParseError
     */
    public function testParseError(string $mimeType, string $json, string $expectedMessage)
    {
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->method('getInfo')
            ->willReturnMap([
                ['http_code', 400],
                ['url', 'http://example.com'],
                ['response_headers', [
                    'HTTP/1.1 400 Bad Request',
                    'Content-Type: '.$mimeType,
                ]],
            ]);
        $response->method('getContent')->willReturn($json);

        $e = new TestException($response);
        $this->assertSame(400, $e->getCode());
        $this->assertSame($expectedMessage, $e->getMessage());
    }
}

class TestException extends \Exception
{
    use HttpExceptionTrait;
}
