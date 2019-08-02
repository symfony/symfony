<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorRenderer\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\ErrorRenderer\DependencyInjection\LazyLoadingErrorRenderer;
use Symfony\Component\ErrorRenderer\ErrorRenderer\ErrorRendererInterface;
use Symfony\Component\ErrorRenderer\Exception\FlattenException;

class LazyLoadingErrorRendererTest extends TestCase
{
    public function testInvalidErrorRenderer()
    {
        $this->expectException('Symfony\Component\ErrorRenderer\Exception\ErrorRendererNotFoundException');
        $this->expectExceptionMessage('No error renderer found for format "foo".');
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $container->expects($this->once())->method('has')->with('foo')->willReturn(false);

        $exception = FlattenException::createFromThrowable(new \Exception('Foo'));
        (new LazyLoadingErrorRenderer($container))->render($exception, 'foo');
    }

    public function testCustomErrorRenderer()
    {
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
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

        $errorRenderer = new LazyLoadingErrorRenderer($container);

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
