<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\Test\ServiceLocatorTest as LegacyServiceLocatorTestCase;
use Symfony\Contracts\Service\Test\ServiceLocatorTestCase;

if (!class_exists(ServiceLocatorTestCase::class)) {
    class_alias(LegacyServiceLocatorTestCase::class, ServiceLocatorTestCase::class);
}

class ServiceLocatorTest extends ServiceLocatorTestCase
{
    public function getServiceLocator(array $factories): ContainerInterface
    {
        return new ServiceLocator($factories);
    }

    public function testGetThrowsOnUndefinedService()
    {
        $this->expectException(NotFoundExceptionInterface::class);
        $this->expectExceptionMessage('Service "dummy" not found: the container inside "Symfony\Component\DependencyInjection\Tests\ServiceLocatorTest" is a smaller service locator that only knows about the "foo" and "bar" services.');
        $locator = $this->getServiceLocator([
            'foo' => fn () => 'bar',
            'bar' => fn () => 'baz',
        ]);

        $locator->get('dummy');
    }

    public function testThrowsOnCircularReference()
    {
        $this->expectException(ServiceCircularReferenceException::class);
        $this->expectExceptionMessage('Circular reference detected for service "bar", path: "bar -> baz -> bar".');
        parent::testThrowsOnCircularReference();
    }

    public function testThrowsInServiceSubscriber()
    {
        $this->expectException(NotFoundExceptionInterface::class);
        $this->expectExceptionMessage('Service "foo" not found: even though it exists in the app\'s container, the container inside "caller" is a smaller service locator that only knows about the "bar" service. Unless you need extra laziness, try using dependency injection instead. Otherwise, you need to declare it using "SomeServiceSubscriber::getSubscribedServices()".');
        $container = new Container();
        $container->set('foo', new \stdClass());
        $subscriber = new SomeServiceSubscriber();
        $subscriber->container = $this->getServiceLocator(['bar' => function () {}]);
        $subscriber->container = $subscriber->container->withContext('caller', $container);

        $subscriber->getFoo();
    }

    public function testGetThrowsServiceNotFoundException()
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('Service "foo" not found: even though it exists in the app\'s container, the container inside "foo" is a smaller service locator that is empty... Try using dependency injection instead.');
        $container = new Container();
        $container->set('foo', new \stdClass());

        $locator = new ServiceLocator([]);
        $locator = $locator->withContext('foo', $container);
        $locator->get('foo');
    }

    public function testInvoke()
    {
        $locator = $this->getServiceLocator([
            'foo' => fn () => 'bar',
            'bar' => fn () => 'baz',
        ]);

        $this->assertSame('bar', $locator('foo'));
        $this->assertSame('baz', $locator('bar'));
        $this->assertNull($locator('dummy'), '->__invoke() should return null on invalid service');
    }

    public function testProvidesServicesInformation()
    {
        $locator = new ServiceLocator([
            'foo' => fn () => 'bar',
            'bar' => fn (): string => 'baz',
            'baz' => fn (): ?string => 'zaz',
        ]);

        $this->assertSame($locator->getProvidedServices(), [
            'foo' => '?',
            'bar' => 'string',
            'baz' => '?string',
        ]);
    }
}

class SomeServiceSubscriber implements ServiceSubscriberInterface
{
    public $container;

    public function getFoo()
    {
        return $this->container->get('foo');
    }

    public static function getSubscribedServices(): array
    {
        return ['bar' => 'stdClass'];
    }
}
