<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorRenderer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorRenderer\ErrorRenderer;
use Symfony\Component\ErrorRenderer\ErrorRenderer\ErrorRendererInterface;
use Symfony\Component\ErrorRenderer\Exception\FlattenException;

class ErrorRendererTest extends TestCase
{
    public function testErrorRendererNotFound()
    {
        $this->expectException('Symfony\Component\ErrorRenderer\Exception\ErrorRendererNotFoundException');
        $this->expectExceptionMessage('No error renderer found for format "foo".');
        $exception = FlattenException::createFromThrowable(new \Exception('foo'));
        (new ErrorRenderer([]))->render($exception, 'foo');
    }

    public function testInvalidErrorRenderer()
    {
        $this->expectException('TypeError');
        new ErrorRenderer([new \stdClass()]);
    }

    public function testCustomErrorRenderer()
    {
        $renderers = [new FooErrorRenderer()];
        $errorRenderer = new ErrorRenderer($renderers);

        $exception = FlattenException::createFromThrowable(new \RuntimeException('Foo'));
        $this->assertSame('Foo', $errorRenderer->render($exception, 'foo'));
    }
}

class FooErrorRenderer implements ErrorRendererInterface
{
    public static function getFormat(): string
    {
        return 'foo';
    }

    public function render(FlattenException $exception): string
    {
        return $exception->getMessage();
    }
}
