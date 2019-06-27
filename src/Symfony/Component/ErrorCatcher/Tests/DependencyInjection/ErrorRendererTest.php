<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorCatcher\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorCatcher\DependencyInjection\ErrorRenderer;
use Symfony\Component\ErrorCatcher\ErrorRenderer\ErrorRendererInterface;
use Symfony\Component\ErrorCatcher\Exception\FlattenException;

class ErrorRendererTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\ErrorCatcher\Exception\ErrorRendererNotFoundException
     * @expectedExceptionMessage No error renderer found for format "foo".
     */
    public function testInvalidErrorRenderer()
    {
        $container = $this->getMockBuilder('Psr\Container\ContainerInterface')->getMock();
        $container->expects($this->once())->method('has')->with('foo')->willReturn(false);

        $exception = FlattenException::create(new \Exception('Foo'));
        (new ErrorRenderer($container))->render($exception, 'foo');
    }

    public function testCustomErrorRenderer()
    {
        $container = $this->getMockBuilder('Psr\Container\ContainerInterface')->getMock();
        $container
            ->expects($this->once())
            ->method('has')
            ->with('foo')
            ->willReturn(true)
        ;
        $container
            ->expects($this->once())
            ->method('get')
            ->willReturn(new FooErrorRenderer())
        ;

        $errorRenderer = new ErrorRenderer($container);

        $exception = FlattenException::create(new \RuntimeException('Foo'));
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
