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
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\ErrorRenderer\DependencyInjection\ErrorRendererPass;
use Symfony\Component\ErrorRenderer\DependencyInjection\LazyLoadingErrorRenderer;
use Symfony\Component\ErrorRenderer\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorRenderer\ErrorRenderer\JsonErrorRenderer;

class ErrorRendererPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $definition = $container->register('error_renderer', LazyLoadingErrorRenderer::class)
            ->addArgument([])
        ;
        $container->register('error_renderer.renderer.html', HtmlErrorRenderer::class)
            ->addTag('error_renderer.renderer')
        ;
        $container->register('error_renderer.renderer.json', JsonErrorRenderer::class)
            ->addTag('error_renderer.renderer')
        ;

        (new ErrorRendererPass())->process($container);

        $serviceLocatorDefinition = $container->getDefinition((string) $definition->getArgument(0));
        $this->assertSame(ServiceLocator::class, $serviceLocatorDefinition->getClass());

        $expected = [
            'html' => new ServiceClosureArgument(new Reference('error_renderer.renderer.html')),
            'json' => new ServiceClosureArgument(new Reference('error_renderer.renderer.json')),
        ];
        $this->assertEquals($expected, $serviceLocatorDefinition->getArgument(0));
    }

    public function testServicesAreOrderedAccordingToPriority()
    {
        $container = new ContainerBuilder();
        $definition = $container->register('error_renderer')->setArguments([null]);
        $container->register('r2')->addTag('error_renderer.renderer', ['format' => 'json', 'priority' => 100]);
        $container->register('r1')->addTag('error_renderer.renderer', ['format' => 'json', 'priority' => 200]);
        $container->register('r3')->addTag('error_renderer.renderer', ['format' => 'json']);
        (new ErrorRendererPass())->process($container);

        $expected = [
            'json' => new ServiceClosureArgument(new Reference('r1')),
        ];
        $serviceLocatorDefinition = $container->getDefinition((string) $definition->getArgument(0));
        $this->assertEquals($expected, $serviceLocatorDefinition->getArgument(0));
    }
}
