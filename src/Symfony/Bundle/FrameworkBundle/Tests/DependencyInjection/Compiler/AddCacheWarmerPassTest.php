<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddCacheWarmerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class AddCacheWarmerPassTest extends TestCase
{
    public function testThatCacheWarmersAreProcessedInPriorityOrder()
    {
        $container = new ContainerBuilder();
        $cacheWarmerDefinition = $container->register('cache_warmer')->addArgument(array());
        $container->register('my_cache_warmer_service1')->addTag('kernel.cache_warmer', array('priority' => 100));
        $container->register('my_cache_warmer_service2')->addTag('kernel.cache_warmer', array('priority' => 200));
        $container->register('my_cache_warmer_service3')->addTag('kernel.cache_warmer');

        $addCacheWarmerPass = new AddCacheWarmerPass();
        $addCacheWarmerPass->process($container);

        $this->assertEquals(
            array(
                new Reference('my_cache_warmer_service2'),
                new Reference('my_cache_warmer_service1'),
                new Reference('my_cache_warmer_service3'),
            ),
            $cacheWarmerDefinition->getArgument(0)
        );
    }

    public function testThatCompilerPassIsIgnoredIfThereIsNoCacheWarmerDefinition()
    {
        $container = new ContainerBuilder();

        $addCacheWarmerPass = new AddCacheWarmerPass();
        $addCacheWarmerPass->process($container);

        // we just check that the pass does not break if no cache warmer is registered
        $this->addToAssertionCount(1);
    }

    public function testThatCacheWarmersMightBeNotDefined()
    {
        $container = new ContainerBuilder();
        $cacheWarmerDefinition = $container->register('cache_warmer')->addArgument(array());

        $addCacheWarmerPass = new AddCacheWarmerPass();
        $addCacheWarmerPass->process($container);

        $this->assertSame(array(), $cacheWarmerDefinition->getArgument(0));
    }
}
