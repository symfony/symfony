<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\ResolveNoPreloadPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ResolveNoPreloadPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();

        $container->register('entry_point')
            ->setPublic(true)
            ->addArgument(new Reference('preloaded'))
            ->addArgument(new Reference('not_preloaded'));

        $container->register('preloaded')
            ->addArgument(new Reference('preloaded_dep'))
            ->addArgument(new Reference('common_dep'));

        $container->register('not_preloaded')
            ->setPublic(true)
            ->addTag('container.no_preload')
            ->addArgument(new Reference('not_preloaded_dep'))
            ->addArgument(new Reference('common_dep'));

        $container->register('preloaded_dep');
        $container->register('not_preloaded_dep');
        $container->register('common_dep');

        (new ResolveNoPreloadPass())->process($container);

        $this->assertFalse($container->getDefinition('entry_point')->hasTag('container.no_preload'));
        $this->assertFalse($container->getDefinition('preloaded')->hasTag('container.no_preload'));
        $this->assertFalse($container->getDefinition('preloaded_dep')->hasTag('container.no_preload'));
        $this->assertFalse($container->getDefinition('common_dep')->hasTag('container.no_preload'));
        $this->assertTrue($container->getDefinition('not_preloaded')->hasTag('container.no_preload'));
        $this->assertTrue($container->getDefinition('not_preloaded_dep')->hasTag('container.no_preload'));
    }
}
