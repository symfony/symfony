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

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\RegisterServiceSubscribersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\DependencyInjection\TypedReference;

require_once __DIR__.'/../Fixtures/includes/classes.php';

class RegisterServiceSubscribersPassTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Service "foo" must implement interface "Symfony\Component\DependencyInjection\ServiceSubscriberInterface".
     */
    public function testInvalidClass()
    {
        $container = new ContainerBuilder();

        $container->register('foo', 'stdClass')
            ->addTag('container.service_subscriber')
        ;

        $pass = new RegisterServiceSubscribersPass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage The "container.service_subscriber" tag accepts only the "key" and "id" attributes, "bar" given for service "foo".
     */
    public function testInvalidAttributes()
    {
        $container = new ContainerBuilder();

        $container->register('foo', 'TestServiceSubscriber')
            ->addTag('container.service_subscriber', array('bar' => '123'))
        ;

        $pass = new RegisterServiceSubscribersPass();
        $pass->process($container);
    }

    public function testNoAttributes()
    {
        $container = new ContainerBuilder();

        $container->register('foo', 'TestServiceSubscriber')
            ->addArgument(new Reference('container'))
            ->addTag('container.service_subscriber')
        ;

        $pass = new RegisterServiceSubscribersPass();
        $pass->process($container);

        $foo = $container->getDefinition('foo');
        $locator = $container->getDefinition((string) $foo->getArgument(0));

        $this->assertFalse($locator->isAutowired());
        $this->assertFalse($locator->isPublic());
        $this->assertSame(ServiceLocator::class, $locator->getClass());

        $expected = array(
            'TestServiceSubscriber' => new ServiceClosureArgument(new TypedReference('TestServiceSubscriber', 'TestServiceSubscriber')),
            'stdClass' => new ServiceClosureArgument(new TypedReference('stdClass', 'stdClass', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)),
            'bar' => new ServiceClosureArgument(new TypedReference('stdClass', 'stdClass')),
            'baz' => new ServiceClosureArgument(new TypedReference('stdClass', 'stdClass', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)),
        );

        $this->assertEquals($expected, $locator->getArgument(0));
    }

    public function testWithAttributes()
    {
        $container = new ContainerBuilder();

        $container->register('foo', 'TestServiceSubscriber')
            ->setAutowired(true)
            ->addArgument(new Reference('container'))
            ->addTag('container.service_subscriber', array('key' => 'bar', 'id' => 'bar'))
            ->addTag('container.service_subscriber', array('key' => 'bar', 'id' => 'baz')) // should be ignored: the first wins
        ;

        $pass = new RegisterServiceSubscribersPass();
        $pass->process($container);

        $foo = $container->getDefinition('foo');
        $locator = $container->getDefinition((string) $foo->getArgument(0));

        $this->assertTrue($locator->isAutowired());
        $this->assertFalse($locator->isPublic());
        $this->assertSame(ServiceLocator::class, $locator->getClass());

        $expected = array(
            'TestServiceSubscriber' => new ServiceClosureArgument(new TypedReference('TestServiceSubscriber', 'TestServiceSubscriber')),
            'stdClass' => new ServiceClosureArgument(new TypedReference('stdClass', 'stdClass', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)),
            'bar' => new ServiceClosureArgument(new TypedReference('bar', 'stdClass')),
            'baz' => new ServiceClosureArgument(new TypedReference('stdClass', 'stdClass', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)),
        );

        $this->assertEquals($expected, $locator->getArgument(0));
    }
}
