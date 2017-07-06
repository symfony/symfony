<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\AddCacheClearerPass;

class AddCacheClearerPassTest extends TestCase
{
    public function testThatCacheClearer()
    {
        $container = new ContainerBuilder();

        $definition = $container->register('cache_clearer')->addArgument(null);
        $container->register('my_cache_clearer_service1')->addTag('kernel.cache_clearer');

        $addCacheWarmerPass = new AddCacheClearerPass();
        $addCacheWarmerPass->process($container);

        $expected = array(
            new Reference('my_cache_clearer_service1'),
        );
        $this->assertEquals($expected, $definition->getArgument(0));
    }

    public function testThatCompilerPassIsIgnoredIfThereIsNoCacheClearerDefinition()
    {
        $definition = $this->getMockBuilder('Symfony\Component\DependencyInjection\Definition')->getMock();
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('hasDefinition', 'findTaggedServiceIds', 'getDefinition'))->getMock();

        $container->expects($this->never())->method('findTaggedServiceIds');
        $container->expects($this->never())->method('getDefinition');
        $container->expects($this->atLeastOnce())
            ->method('hasDefinition')
            ->with('cache_clearer')
            ->will($this->returnValue(false));
        $definition->expects($this->never())->method('replaceArgument');

        $addCacheWarmerPass = new AddCacheClearerPass();
        $addCacheWarmerPass->process($container);
    }
}
