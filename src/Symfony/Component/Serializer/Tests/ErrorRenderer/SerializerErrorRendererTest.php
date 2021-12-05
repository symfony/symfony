<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\ErrorRenderer;

use function json_decode;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\ErrorRenderer\SerializerErrorRenderer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * @author Alexey Deriyenko <alexey.deriyenko@gmail.com>
 */
class SerializerErrorRendererTest extends TestCase
{
    /**
     * @dataProvider getRenderData
     */
    public function testRenderReturnsJson(\Throwable $exception, SerializerErrorRenderer $serializerErrorRenderer)
    {
        $this->assertJson($serializerErrorRenderer->render($exception)->getAsString());
    }

    /**
     * @dataProvider getRenderData
     */
    public function testRenderReturnsJsonWithCorrectStatusCode(\Throwable $exception, SerializerErrorRenderer $serializerErrorRenderer, int $expectedStatusCode)
    {
        $statusCodeFromJson = json_decode($serializerErrorRenderer->render($exception)->getAsString())->statusCode;
        $this->assertEquals($expectedStatusCode, $statusCodeFromJson);
    }

    /**
     * @dataProvider getRenderData
     */
    public function testRenderReturnsJsonWithCorrectStatusText(\Throwable $exception, SerializerErrorRenderer $serializerErrorRenderer, int $expectedStatusCode, string $expectedStatusText)
    {
        $statusTextFromJson = json_decode($serializerErrorRenderer->render($exception)->getAsString())->statusText;
        $this->assertEquals($expectedStatusText, $statusTextFromJson);
    }

    public function getRenderData(): iterable
    {
        yield '->render() returns the JSON content without exception mapping config' => [
            new \RuntimeException('Foo'),
            new SerializerErrorRenderer(new Serializer([new ObjectNormalizer()], [new JsonEncoder()]), 'json'),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR],
        ];

        yield '->render() returns the JSON content with exception mapping config' => [
            new \RuntimeException('Foo'),
            new SerializerErrorRenderer(new Serializer([new ObjectNormalizer()], [new JsonEncoder()]), 'json', null, false, [
                \RuntimeException::class => [
                    'status_code' => Response::HTTP_I_AM_A_TEAPOT,
                    'log_level' => null,
                ],
            ]),
            Response::HTTP_I_AM_A_TEAPOT,
            Response::$statusTexts[Response::HTTP_I_AM_A_TEAPOT],
        ];
    }
}
