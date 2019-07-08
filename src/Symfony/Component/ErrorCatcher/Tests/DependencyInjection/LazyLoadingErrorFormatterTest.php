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
use Psr\Container\ContainerInterface;
use Symfony\Component\ErrorCatcher\DependencyInjection\LazyLoadingErrorFormatter;
use Symfony\Component\ErrorCatcher\ErrorRenderer\ErrorRendererInterface;
use Symfony\Component\ErrorCatcher\Exception\FlattenException;

class LazyLoadingErrorFormatterTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\ErrorCatcher\Exception\ErrorRendererNotFoundException
     * @expectedExceptionMessage No error renderer found for format "foo".
     */
    public function testInvalidErrorRenderer()
    {
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $container->expects($this->once())->method('has')->with('foo')->willReturn(false);

        $exception = FlattenException::createFromThrowable(new \Exception('Foo'));
        (new LazyLoadingErrorFormatter($container))->render($exception, 'foo');
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

        $errorRenderer = new LazyLoadingErrorFormatter($container);

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
