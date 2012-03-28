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

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * This class tests the integration of the different compiler passes
 */
class IntegrationTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\Config\Resource\FileResource')) {
            $this->markTestSkipped('The "Config" component is not available');
        }
    }

    /**
     * This tests that the following dependencies are correctly processed:
     *
     * A is public, B/C are private
     * A -> C
     * B -> C
     */
    public function testProcessRemovesAndInlinesRecursively()
    {
        $container = new ContainerBuilder();

        $a = $container
            ->register('a', '\stdClass')
            ->addArgument(new Reference('c'))
        ;

        $b = $container
            ->register('b', '\stdClass')
            ->addArgument(new Reference('c'))
            ->setPublic(false)
        ;

        $c = $container
            ->register('c', '\stdClass')
            ->setPublic(false)
        ;

        $container->compile();

        $this->assertTrue($container->hasDefinition('a'));
        $arguments = $a->getArguments();
        $this->assertSame($c, $arguments[0]);
        $this->assertFalse($container->hasDefinition('b'));
        $this->assertFalse($container->hasDefinition('c'));
    }

    public function testProcessInlinesReferencesToAliases()
    {
        $container = new ContainerBuilder();

        $a = $container
            ->register('a', '\stdClass')
            ->addArgument(new Reference('b'))
        ;

        $container->setAlias('b', new Alias('c', false));

        $c = $container
            ->register('c', '\stdClass')
            ->setPublic(false)
        ;

        $container->compile();

        $this->assertTrue($container->hasDefinition('a'));
        $arguments = $a->getArguments();
        $this->assertSame($c, $arguments[0]);
        $this->assertFalse($container->hasAlias('b'));
        $this->assertFalse($container->hasDefinition('c'));
    }

    public function testProcessInlinesWhenThereAreMultipleReferencesButFromTheSameDefinition()
    {
        $container = new ContainerBuilder();

        $container
            ->register('a', '\stdClass')
            ->addArgument(new Reference('b'))
            ->addMethodCall('setC', array(new Reference('c')))
        ;

        $container
            ->register('b', '\stdClass')
            ->addArgument(new Reference('c'))
            ->setPublic(false)
        ;

        $container
            ->register('c', '\stdClass')
            ->setPublic(false)
        ;

        $container->compile();

        $this->assertTrue($container->hasDefinition('a'));
        $this->assertFalse($container->hasDefinition('b'));
        $this->assertFalse($container->hasDefinition('c'), 'Service C was not inlined.');
    }
}
