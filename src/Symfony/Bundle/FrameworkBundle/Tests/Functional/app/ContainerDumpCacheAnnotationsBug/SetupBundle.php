<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\app\ContainerDumpCacheAnnotationsBug;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BeforeOptimizationCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        // force to use service in compiler pass which does DiExtraBundle
        $container->get('cache.annotations');
    }
}

/**
 * Emulate CacheCollectorPass which aliasias cache.annotations service (used in debug mode).
 *
 * @see src/Symfony/Bundle/FrameworkBundle/DependencyInjection/Compiler/CacheCollectorPass.php:24
 */
class CacheCollectorEmulationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $id = 'cache.annotations';

        $container->register($id.'.recorder', DummyDecorator::class)
            ->setDecoratedService($id)
            ->addArgument(new Reference($id.'.recorder.inner'))
            ->setPublic(false);
    }
}

class DummyDecorator implements CacheItemPoolInterface
{
    public function getItem($key)
    {
    }

    public function getItems(array $keys = array())
    {
    }

    public function hasItem($key)
    {
    }

    public function clear()
    {
    }

    public function deleteItem($key)
    {
    }

    public function deleteItems(array $keys)
    {
    }

    public function save(CacheItemInterface $item)
    {
    }

    public function saveDeferred(CacheItemInterface $item)
    {
    }

    public function commit()
    {
    }
}

class SetupBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new BeforeOptimizationCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
        $container->addCompilerPass(new CacheCollectorEmulationPass());
    }
}
