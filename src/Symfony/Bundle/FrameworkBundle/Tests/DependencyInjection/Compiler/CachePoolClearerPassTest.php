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
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\CachePoolClearerPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\CachePoolPass;
use Symfony\Component\DependencyInjection\Compiler\RemoveUnusedDefinitionsPass;
use Symfony\Component\DependencyInjection\Compiler\RepeatedPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class CachePoolClearerPassTest extends TestCase
{
    public function testPoolRefsAreWeak()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.name', 'app');
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.root_dir', 'foo');

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

        $this->assertEquals(array(array('addPool', array(new Reference('public.pool')))), $clearer->getMethodCalls());
    }
}
