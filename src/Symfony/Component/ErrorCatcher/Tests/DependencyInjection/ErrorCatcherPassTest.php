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
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\ErrorCatcher\DependencyInjection\ErrorCatcherPass;
use Symfony\Component\ErrorCatcher\DependencyInjection\LazyLoadingErrorFormatter;
use Symfony\Component\ErrorCatcher\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorCatcher\ErrorRenderer\JsonErrorRenderer;

class ErrorCatcherPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $definition = $container->register('error_catcher.error_formatter', LazyLoadingErrorFormatter::class)
            ->addArgument([])
        ;
        $container->register('error_catcher.renderer.html', HtmlErrorRenderer::class)
            ->addTag('error_catcher.renderer')
        ;
        $container->register('error_catcher.renderer.json', JsonErrorRenderer::class)
            ->addTag('error_catcher.renderer')
        ;

        (new ErrorCatcherPass())->process($container);

        $serviceLocatorDefinition = $container->getDefinition((string) $definition->getArgument(0));
        $this->assertSame(ServiceLocator::class, $serviceLocatorDefinition->getClass());

        $expected = [
            'html' => new ServiceClosureArgument(new Reference('error_catcher.renderer.html')),
            'json' => new ServiceClosureArgument(new Reference('error_catcher.renderer.json')),
        ];
        $this->assertEquals($expected, $serviceLocatorDefinition->getArgument(0));
    }
}
