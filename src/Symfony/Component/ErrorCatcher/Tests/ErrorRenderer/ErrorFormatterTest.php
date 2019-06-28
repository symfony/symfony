<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorCatcher\Tests\ErrorRenderer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorCatcher\ErrorRenderer\ErrorFormatter;
use Symfony\Component\ErrorCatcher\ErrorRenderer\ErrorRendererInterface;
use Symfony\Component\ErrorCatcher\Exception\FlattenException;

class ErrorFormatterTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\ErrorCatcher\Exception\ErrorRendererNotFoundException
     * @expectedExceptionMessage No error renderer found for format "foo".
     */
    public function testErrorRendererNotFound()
    {
        $exception = FlattenException::createFromThrowable(new \Exception('foo'));
        (new ErrorFormatter([]))->render($exception, 'foo');
    }

    /**
     * @expectedException \TypeError
     */
    public function testInvalidErrorRenderer()
    {
        new ErrorFormatter([new \stdClass()]);
    }

    public function testCustomErrorRenderer()
    {
        $renderers = [new FooErrorRenderer()];
        $errorRenderer = new ErrorFormatter($renderers);

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
