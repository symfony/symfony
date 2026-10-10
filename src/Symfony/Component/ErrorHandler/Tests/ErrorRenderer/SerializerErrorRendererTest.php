<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\Tests\ErrorRenderer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\ErrorRenderer\SerializerErrorRenderer;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ProblemNormalizer;
use Symfony\Component\Serializer\Serializer;

class SerializerErrorRendererTest extends TestCase
{
    public function testDefaultContent()
    {
        $errorRenderer = new SerializerErrorRenderer(new Serializer(), 'html');

        self::assertStringContainsString('<h2>The server returned a "500 Internal Server Error".</h2>', $errorRenderer->render(new \RuntimeException())->getAsString());
    }

    public function testSerializerContent()
    {
        $exception = new \RuntimeException('Foo');
        $errorRenderer = new SerializerErrorRenderer(
            new Serializer([new ProblemNormalizer()], [new JsonEncoder()]),
            static fn () => 'json'
        );

        $flattenException = $errorRenderer->render($exception);

        $this->assertSame('{"type":"https:\/\/tools.ietf.org\/html\/rfc2616#section-10","title":"An error occurred","status":500,"detail":"Internal Server Error"}', $flattenException->getAsString());
        $this->assertSame('application/problem+json', $flattenException->getHeaders()['Content-Type']);
    }

    public function testProblemFormatIsRenderedAsJson()
    {
        $errorRenderer = new SerializerErrorRenderer(new Serializer([new ProblemNormalizer()], [new JsonEncoder()]), 'problem');

        $flattenException = $errorRenderer->render(new \RuntimeException('Foo'));

        $this->assertSame('{"type":"https:\/\/tools.ietf.org\/html\/rfc2616#section-10","title":"An error occurred","status":500,"detail":"Internal Server Error"}', $flattenException->getAsString());
        $this->assertSame('application/problem+json', $flattenException->getHeaders()['Content-Type']);
    }

    public function testCustomNormalizerKeepsJsonContentType()
    {
        $normalizer = new class implements NormalizerInterface {
            public function normalize(mixed $data, ?string $format = null, array $context = []): array
            {
                return ['message' => $data->getMessage()];
            }

            public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
            {
                return $data instanceof FlattenException;
            }

            public function getSupportedTypes(?string $format): array
            {
                return [FlattenException::class => true];
            }
        };
        $errorRenderer = new SerializerErrorRenderer(new Serializer([$normalizer, new ProblemNormalizer()], [new JsonEncoder()]), 'json');

        $flattenException = $errorRenderer->render(new \RuntimeException('Foo'));

        $this->assertSame('{"message":"Foo"}', $flattenException->getAsString());
        $this->assertSame('application/json', $flattenException->getHeaders()['Content-Type']);
    }

    public function testXmlContentType()
    {
        $errorRenderer = new SerializerErrorRenderer(new Serializer([new ProblemNormalizer()], [new XmlEncoder()]), 'xml');

        $flattenException = $errorRenderer->render(new \RuntimeException('Foo'));

        $this->assertStringContainsString('<response><type>', $flattenException->getAsString());
        $this->assertSame('text/xml', $flattenException->getHeaders()['Content-Type']);
    }
}
