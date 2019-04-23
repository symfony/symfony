<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\ErrorHandler\DependencyInjection\ErrorHandlerPass;
use Symfony\Component\ErrorHandler\DependencyInjection\ErrorRenderer;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorHandler\ErrorRenderer\JsonErrorRenderer;

class ErrorHandlerPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $definition = $container->register('error_handler.error_renderer', ErrorRenderer::class)
            ->addArgument([])
        ;
        $container->register('error_handler.renderer.html', HtmlErrorRenderer::class)
            ->addTag('error_handler.renderer')
        ;
        $container->register('error_handler.renderer.json', JsonErrorRenderer::class)
            ->addTag('error_handler.renderer')
        ;

        (new ErrorHandlerPass())->process($container);

        $serviceLocatorDefinition = $container->getDefinition((string) $definition->getArgument(0));
        $this->assertSame(ServiceLocator::class, $serviceLocatorDefinition->getClass());

        $expected = [
            'html' => new ServiceClosureArgument(new Reference('error_handler.renderer.html')),
            'json' => new ServiceClosureArgument(new Reference('error_handler.renderer.json')),
        ];
        $this->assertEquals($expected, $serviceLocatorDefinition->getArgument(0));
    }
}
