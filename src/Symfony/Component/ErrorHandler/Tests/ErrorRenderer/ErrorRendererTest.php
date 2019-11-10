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
use Symfony\Component\ErrorHandler\ErrorRenderer\ErrorRenderer;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRendererInterface;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ProblemNormalizer;
use Symfony\Component\Serializer\Serializer;

class ErrorRendererTest extends TestCase
{
    public function testDefaultContent()
    {
        $errorRenderer = new ErrorRenderer();

        self::assertStringContainsString('<h2>The server returned a "500 Internal Server Error".</h2>', $errorRenderer->render(new \RuntimeException(), 'html'));
    }

    public function testCustomContent()
    {
        $errorRenderer = new ErrorRenderer(new CustomHtmlErrorRenderer());

        $this->assertSame('Foo', $errorRenderer->render(new \RuntimeException('Foo'), 'html'));
    }

    public function testSerializerContent()
    {
        $exception = new \RuntimeException('Foo');
        $errorRenderer = new ErrorRenderer(null, new Serializer([new ProblemNormalizer()], [new JsonEncoder()]));

        $this->assertSame('{"type":"https:\/\/tools.ietf.org\/html\/rfc2616#section-10","title":"An error occurred","status":500,"detail":"Internal Server Error"}', $errorRenderer->render($exception, 'json'));
    }
}

class CustomHtmlErrorRenderer implements HtmlErrorRendererInterface
{
    public function render(FlattenException $exception): string
    {
        return $exception->getMessage();
    }
}
