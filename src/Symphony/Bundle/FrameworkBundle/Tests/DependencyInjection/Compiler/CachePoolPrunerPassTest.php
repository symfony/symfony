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
use Symphony\Bundle\FrameworkBundle\Command\CachePoolPruneCommand;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\CachePoolPrunerPass;
use Symphony\Component\Cache\Adapter\FilesystemAdapter;
use Symphony\Component\Cache\Adapter\PhpFilesAdapter;
use Symphony\Component\DependencyInjection\Argument\IteratorArgument;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Reference;

class CachePoolPrunerPassTest extends TestCase
{
    public function testCompilerPassReplacesCommandArgument()
    {
        $container = new ContainerBuilder();
        $container->register(CachePoolPruneCommand::class)->addArgument(array());
        $container->register('pool.foo', FilesystemAdapter::class)->addTag('cache.pool');
        $container->register('pool.bar', PhpFilesAdapter::class)->addTag('cache.pool');

        $pass = new CachePoolPrunerPass();
        $pass->process($container);

        $expected = array(
            'pool.foo' => new Reference('pool.foo'),
            'pool.bar' => new Reference('pool.bar'),
        );
        $argument = $container->getDefinition(CachePoolPruneCommand::class)->getArgument(0);

        $this->assertInstanceOf(IteratorArgument::class, $argument);
        $this->assertEquals($expected, $argument->getValues());
    }

    public function testCompilePassIsIgnoredIfCommandDoesNotExist()
    {
        $container = new ContainerBuilder();

        $definitionsBefore = count($container->getDefinitions());
        $aliasesBefore = count($container->getAliases());

        $pass = new CachePoolPrunerPass();
        $pass->process($container);

        // the container is untouched (i.e. no new definitions or aliases)
        $this->assertCount($definitionsBefore, $container->getDefinitions());
        $this->assertCount($aliasesBefore, $container->getAliases());
    }

    /**
     * @expectedException \Symphony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Class "Symphony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler\NotFound" used for service "pool.not-found" cannot be found.
     */
    public function testCompilerPassThrowsOnInvalidDefinitionClass()
    {
        $container = new ContainerBuilder();
        $container->register(CachePoolPruneCommand::class)->addArgument(array());
        $container->register('pool.not-found', NotFound::class)->addTag('cache.pool');

        $pass = new CachePoolPrunerPass();
        $pass->process($container);
    }
}
