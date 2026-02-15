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
use Symfony\Bundle\FrameworkBundle\Command\CachePoolPruneCommand;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\DependencyInjection\CachePoolPass;
use Symfony\Component\Cache\DependencyInjection\CachePoolPrunerPass;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class CachePoolPrunerPassTest extends TestCase
{
    public function testCompilerPassReplacesCommandArgument()
    {
        $container = new ContainerBuilder();
        $container->register('console.command.cache_pool_prune')->addArgument([]);
        $container->register('pool.foo', FilesystemAdapter::class)->addTag('cache.pool');
        $container->register('pool.bar', PhpFilesAdapter::class)->addTag('cache.pool');

        $pass = new CachePoolPrunerPass();
        $pass->process($container);

        $expected = [
            'pool.foo' => new Reference('pool.foo'),
            'pool.bar' => new Reference('pool.bar'),
        ];
        $argument = $container->getDefinition('console.command.cache_pool_prune')->getArgument(0);

        $this->assertInstanceOf(IteratorArgument::class, $argument);
        $this->assertEquals($expected, $argument->getValues());
    }

    public function testCompilePassIsIgnoredIfCommandDoesNotExist()
    {
        $container = new ContainerBuilder();

        $definitionsBefore = \count($container->getDefinitions());
        $aliasesBefore = \count($container->getAliases());

        $pass = new CachePoolPrunerPass();
        $pass->process($container);

        // the container is untouched (i.e. no new definitions or aliases)
        $this->assertCount($definitionsBefore, $container->getDefinitions());
        $this->assertCount($aliasesBefore, $container->getAliases());
    }

    public function testNonPruneablePoolsAreNotAdded()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.project_dir', __DIR__);
        $container->setParameter('kernel.container_class', 'TestContainer');

        $container->register('console.command.cache_pool_prune', CachePoolPruneCommand::class)
            ->setArguments([new IteratorArgument([])]);

        $container->register('cache.null', NonPruneableAdapter::class)
            ->setArguments([null])
            ->addTag('cache.pool');

        $container->register('cache.fs', PruneableAdapter::class)
            ->setArguments([null])
            ->addTag('cache.pool');

        (new CachePoolPass())->process($container);
        (new CachePoolPrunerPass())->process($container);

        $arg = $container->getDefinition('console.command.cache_pool_prune')->getArgument(0);
        $values = $arg->getValues();

        $this->assertArrayNotHasKey('cache.null', $values);
        $this->assertArrayHasKey('cache.fs', $values);
    }

    public function testPruneableAttributeOverridesInterfaceCheck()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.project_dir', __DIR__);
        $container->setParameter('kernel.container_class', 'TestContainer');

        $container->register('console.command.cache_pool_prune', 'stdClass')
            ->setArguments([new IteratorArgument([])]);

        $container->register('manual.pool', NonPruneableAdapter::class)
            ->setArguments([null])
            ->addTag('cache.pool', ['pruneable' => true]);

        (new CachePoolPass())->process($container);
        (new CachePoolPrunerPass())->process($container);

        $arg = $container->getDefinition('console.command.cache_pool_prune')->getArgument(0);
        $values = $arg->getValues();

        $this->assertArrayHasKey('manual.pool', $values);
    }
}

class PruneableAdapter implements PruneableInterface
{
    public function prune(): bool
    {
        return true;
    }
}

class NonPruneableAdapter
{
}
