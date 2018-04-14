<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ServiceValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ServiceValueResolverTest extends TestCase
{
    public function testDoNotSupportWhenControllerDoNotExists()
    {
        $resolver = new ServiceValueResolver(new ServiceLocator(array()));
        $argument = new ArgumentMetadata('dummy', DummyService::class, false, false, null);
        $request = $this->requestWithAttributes(array('_controller' => 'my_controller'));

        $this->assertFalse($resolver->supports($request, $argument));
    }

    public function testExistingController()
    {
        $resolver = new ServiceValueResolver(new ServiceLocator(array(
            'App\\Controller\\Mine::method' => function () {
                return new ServiceLocator(array(
                    'dummy' => function () {
                        return new DummyService();
                    },
                ));
            },
        )));

        $request = $this->requestWithAttributes(array('_controller' => 'App\\Controller\\Mine::method'));
        $argument = new ArgumentMetadata('dummy', DummyService::class, false, false, null);

        $this->assertTrue($resolver->supports($request, $argument));
        $this->assertYieldEquals(array(new DummyService()), $resolver->resolve($request, $argument));
    }

    public function testExistingControllerWithATrailingBackSlash()
    {
        $resolver = new ServiceValueResolver(new ServiceLocator(array(
            'App\\Controller\\Mine::method' => function () {
                return new ServiceLocator(array(
                    'dummy' => function () {
                        return new DummyService();
                    },
                ));
            },
        )));

        $request = $this->requestWithAttributes(array('_controller' => '\\App\\Controller\\Mine::method'));
        $argument = new ArgumentMetadata('dummy', DummyService::class, false, false, null);

        $this->assertTrue($resolver->supports($request, $argument));
        $this->assertYieldEquals(array(new DummyService()), $resolver->resolve($request, $argument));
    }

    public function testControllerNameIsAnArray()
    {
        $resolver = new ServiceValueResolver(new ServiceLocator(array(
            'App\\Controller\\Mine::method' => function () {
                return new ServiceLocator(array(
                    'dummy' => function () {
                        return new DummyService();
                    },
                ));
            },
        )));

        $request = $this->requestWithAttributes(array('_controller' => array('App\\Controller\\Mine', 'method')));
        $argument = new ArgumentMetadata('dummy', DummyService::class, false, false, null);

        $this->assertTrue($resolver->supports($request, $argument));
        $this->assertYieldEquals(array(new DummyService()), $resolver->resolve($request, $argument));
    }

    private function requestWithAttributes(array $attributes)
    {
        $request = Request::create('/');

        foreach ($attributes as $name => $value) {
            $request->attributes->set($name, $value);
        }

        return $request;
    }

    private function assertYieldEquals(array $expected, \Generator $generator)
    {
        $args = array();
        foreach ($generator as $arg) {
            $args[] = $arg;
        }

        $this->assertEquals($expected, $args);
    }
}

class DummyService
{
}
