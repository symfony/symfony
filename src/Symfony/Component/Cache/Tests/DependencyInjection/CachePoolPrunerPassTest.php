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
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\DependencyInjection\CachePoolPrunerPass;
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

    public function testCompilerPassThrowsOnInvalidDefinitionClass()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Class "Symfony\Component\Cache\Tests\DependencyInjection\NotFound" used for service "pool.not-found" cannot be found.');
        $container = new ContainerBuilder();
        $container->register('console.command.cache_pool_prune')->addArgument([]);
        $container->register('pool.not-found', NotFound::class)->addTag('cache.pool');

        $pass = new CachePoolPrunerPass();
        $pass->process($container);
    }
}
