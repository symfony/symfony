<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\DependencyInjection\CachePoolClearerPass;
use Symfony\Component\Cache\DependencyInjection\CachePoolPass;
use Symfony\Component\DependencyInjection\Compiler\RemoveUnusedDefinitionsPass;
use Symfony\Component\DependencyInjection\Compiler\RepeatedPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;

class CachePoolClearerPassTest extends TestCase
{
    public function testPoolRefsAreWeak()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.container_class', 'app');
        $container->setParameter('kernel.project_dir', 'foo');

        $globalClearer = new Definition(Psr6CacheClearer::class);
        $container->setDefinition('cache.global_clearer', $globalClearer);

        $publicPool = new Definition();
        $publicPool->addArgument('namespace');
        $publicPool->addTag('cache.pool', ['clearer' => 'clearer_alias']);
        $container->setDefinition('public.pool', $publicPool);

        $publicPool = new Definition();
        $publicPool->addArgument('namespace');
        $publicPool->addTag('cache.pool', ['clearer' => 'clearer_alias', 'name' => 'pool2']);
        $container->setDefinition('public.pool2', $publicPool);

        $privatePool = new Definition();
        $privatePool->setPublic(false);
        $privatePool->addArgument('namespace');
        $privatePool->addTag('cache.pool', ['clearer' => 'clearer_alias']);
        $container->setDefinition('private.pool', $privatePool);

        $clearer = new Definition();
        $container->setDefinition('clearer', $clearer);
        $container->setAlias('clearer_alias', 'clearer');

        $pass = new RemoveUnusedDefinitionsPass();
        foreach ($container->getCompiler()->getPassConfig()->getRemovingPasses() as $removingPass) {
            if ($removingPass instanceof RepeatedPass) {
                $pass->setRepeatedPass(new RepeatedPass([$pass]));
                break;
            }
        }
        foreach ([new CachePoolPass(), $pass, new CachePoolClearerPass()] as $pass) {
            $pass->process($container);
        }

        $expected = [[
            'public.pool' => new Reference('public.pool'),
            'pool2' => new Reference('public.pool2'),
        ]];
        $this->assertEquals($expected, $clearer->getArguments());
        $this->assertEquals($expected, $globalClearer->getArguments());
    }
}
