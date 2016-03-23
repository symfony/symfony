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

use Symfony\Component\DependencyInjection\AmbiguousDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class RegisterClassNamedServicesPassTest extends \PHPUnit_Framework_TestCase
{
    /** @var ContainerBuilder */
    private $container;

    protected function setUp()
    {
        $this->container = new ContainerBuilder();
        require_once __DIR__.'/../Fixtures/includes/classes2.php';
    }

    public function testRegisterFqcnServices()
    {
        $container = $this->container;
        $container->register('foo', ClassNamedServices\A::class);
        $container->compile();

        $serviceIds = $container->getServiceIds();
        $classes = array_map('strtolower', array(
            ClassNamedServices\IA::class,
            ClassNamedServices\IB::class,
            ClassNamedServices\IC::class,
            ClassNamedServices\A::class,
            ClassNamedServices\B::class,
            ClassNamedServices\C::class,
        ));

        foreach ($classes as $class) {
            $this->assertContains($class, $serviceIds);
        }
    }

    public function testRegisterFqcnServicesAsAliases()
    {
        $container = $this->container;
        $container->register('foo', ClassNamedServices\A::class);
        $container->compile();

        $this->assertTrue($container->hasAlias(ClassNamedServices\A::class));
        $this->assertTrue($container->hasAlias(ClassNamedServices\IC::class));
        $this->assertEquals($container->findDefinition(ClassNamedServices\B::class), $container->findDefinition(ClassNamedServices\IC::class));
    }

    public function testNotRegisterForPrivateServices()
    {
        $container = $this->container;
        $definition = $container->register('bar', ClassNamedServices\E::class);
        $definition->setPublic(false);

        $definition = $container->register('foo', ClassNamedServices\A::class);
        $definition->setArguments(array(new Reference(ClassNamedServices\E::class)));
        $container->compile();

        $this->assertFalse($container->hasAlias(ClassNamedServices\E::class));
        $this->assertInstanceOf(Definition::class, $container->getDefinition('foo')->getArgument(0));
    }

    public function testRegisterAmbiguousDefinition()
    {
        $container = $this->container;
        $container->register('foo', ClassNamedServices\E::class);
        $container->register('bar', ClassNamedServices\E::class);

        $container->compile();

        $this->assertInstanceOf(AmbiguousDefinition::class, $container->getDefinition(ClassNamedServices\E::class));
    }
}
