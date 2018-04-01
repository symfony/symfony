<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\CachePoolClearerPass;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\CachePoolPass;
use Symphony\Component\DependencyInjection\Compiler\RemoveUnusedDefinitionsPass;
use Symphony\Component\DependencyInjection\Compiler\RepeatedPass;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Definition;
use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;

class CachePoolClearerPassTest extends TestCase
{
    public function testPoolRefsAreWeak()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.name', 'app');
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.root_dir', 'foo');

        $globalClearer = new Definition(Psr6CacheClearer::class);
        $container->setDefinition('cache.global_clearer', $globalClearer);

        $publicPool = new Definition();
        $publicPool->addArgument('namespace');
        $publicPool->addTag('cache.pool', array('clearer' => 'clearer_alias'));
        $container->setDefinition('public.pool', $publicPool);

        $privatePool = new Definition();
        $privatePool->setPublic(false);
        $privatePool->addArgument('namespace');
        $privatePool->addTag('cache.pool', array('clearer' => 'clearer_alias'));
        $container->setDefinition('private.pool', $privatePool);

        $clearer = new Definition();
        $container->setDefinition('clearer', $clearer);
        $container->setAlias('clearer_alias', 'clearer');

        $pass = new RemoveUnusedDefinitionsPass();
        $pass->setRepeatedPass(new RepeatedPass(array($pass)));
        foreach (array(new CachePoolPass(), $pass, new CachePoolClearerPass()) as $pass) {
            $pass->process($container);
        }

        $this->assertEquals(array(array('public.pool' => new Reference('public.pool'))), $clearer->getArguments());
        $this->assertEquals(array(array('public.pool' => new Reference('public.pool'))), $globalClearer->getArguments());
    }
}
