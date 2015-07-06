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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Compiler\ResolveInvalidReferencesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ResolveInvalidReferencesPassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $def = $container
            ->register('foo')
            ->setArguments(array(new Reference('bar', ContainerInterface::NULL_ON_INVALID_REFERENCE)))
            ->addMethodCall('foo', array(new Reference('moo', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)))
        ;

        $this->process($container);

        $arguments = $def->getArguments();
        $this->assertNull($arguments[0]);
        $this->assertCount(0, $def->getMethodCalls());
    }

    public function testProcessIgnoreNonExistentServices()
    {
        $container = new ContainerBuilder();
        $def = $container
            ->register('foo')
            ->setArguments(array(new Reference('bar')))
        ;

        $this->process($container);

        $arguments = $def->getArguments();
        $this->assertEquals('bar', (string) $arguments[0]);
    }

    public function testProcessRemovesPropertiesOnInvalid()
    {
        $container = new ContainerBuilder();
        $def = $container
            ->register('foo')
            ->setProperty('foo', new Reference('bar', ContainerInterface::IGNORE_ON_INVALID_REFERENCE))
        ;

        $this->process($container);

        $this->assertEquals(array(), $def->getProperties());
    }

    /**
     * @group legacy
     */
    public function testStrictFlagIsPreserved()
    {
        $container = new ContainerBuilder();
        $container->register('bar');
        $def = $container
            ->register('foo')
            ->addArgument(new Reference('bar', ContainerInterface::NULL_ON_INVALID_REFERENCE, false))
        ;

        $this->process($container);

        $this->assertFalse($def->getArgument(0)->isStrict());
    }

    protected function process(ContainerBuilder $container)
    {
        $pass = new ResolveInvalidReferencesPass();
        $pass->process($container);
    }
}
