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
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ControllerNotRegisteredAsServiceValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ControllerNotRegisteredAsServiceValueResolverTest extends TestCase
{
    public function testDoSupportWhenControllerDoNotExists()
    {
        $resolver = new ControllerNotRegisteredAsServiceValueResolver(new ServiceLocator([]));
        $argument = new ArgumentMetadata('dummy', \stdClass::class, false, false, null);
        $request = $this->requestWithAttributes(['_controller' => 'my_controller']);

        $this->assertTrue($resolver->supports($request, $argument));
    }

    public function testDoNotSupportWhenControllerExists()
    {
        $resolver = new ControllerNotRegisteredAsServiceValueResolver(new ServiceLocator([
            'App\\Controller\\Mine::method' => function () {
                return new ServiceLocator([
                    'dummy' => function () {
                        return new \stdClass();
                    },
                ]);
            },
        ]));
        $argument = new ArgumentMetadata('dummy', \stdClass::class, false, false, null);
        $request = $this->requestWithAttributes(['_controller' => 'App\\Controller\\Mine::method']);

        $this->assertFalse($resolver->supports($request, $argument));
    }

    public function testDoNotSupportEmptyController()
    {
        $resolver = new ControllerNotRegisteredAsServiceValueResolver(new ServiceLocator([]));
        $argument = new ArgumentMetadata('dummy', \stdClass::class, false, false, null);
        $request = $this->requestWithAttributes(['_controller' => '']);

        $this->assertFalse($resolver->supports($request, $argument));
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Could not resolve argument $dummy of "App\Controller\Mine::method()" maybe you forgot to register the controller as a service or missed tagging the controller with the "controller.service_arguments" tag?
     */
    public function testController()
    {
        $resolver = new ControllerNotRegisteredAsServiceValueResolver(new ServiceLocator([]));
        $argument = new ArgumentMetadata('dummy', \stdClass::class, false, false, null);
        $request = $this->requestWithAttributes(['_controller' => 'App\\Controller\\Mine::method']);

        $this->assertTrue($resolver->supports($request, $argument));
        $resolver->resolve($request, $argument);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Could not resolve argument $dummy of "App\Controller\Mine::method()" maybe you forgot to register the controller as a service or missed tagging the controller with the "controller.service_arguments" tag?
     */
    public function testControllerWithATrailingBackSlash()
    {
        $resolver = new ControllerNotRegisteredAsServiceValueResolver(new ServiceLocator([]));
        $argument = new ArgumentMetadata('dummy', \stdClass::class, false, false, null);
        $request = $this->requestWithAttributes(['_controller' => '\\App\\Controller\\Mine::method']);

        $this->assertTrue($resolver->supports($request, $argument));
        $resolver->resolve($request, $argument);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Could not resolve argument $dummy of "App\Controller\Mine::method()" maybe you forgot to register the controller as a service or missed tagging the controller with the "controller.service_arguments" tag?
     */
    public function testControllerWithMethodNameStartUppercase()
    {
        $resolver = new ControllerNotRegisteredAsServiceValueResolver(new ServiceLocator([]));
        $argument = new ArgumentMetadata('dummy', \stdClass::class, false, false, null);
        $request = $this->requestWithAttributes(['_controller' => 'App\\Controller\\Mine::Method']);

        $this->assertTrue($resolver->supports($request, $argument));
        $resolver->resolve($request, $argument);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Could not resolve argument $dummy of "App\Controller\Mine::method()" maybe you forgot to register the controller as a service or missed tagging the controller with the "controller.service_arguments" tag?
     */
    public function testControllerNameIsAnArray()
    {
        $resolver = new ControllerNotRegisteredAsServiceValueResolver(new ServiceLocator([]));
        $argument = new ArgumentMetadata('dummy', \stdClass::class, false, false, null);
        $request = $this->requestWithAttributes(['_controller' => ['App\\Controller\\Mine', 'method']]);

        $this->assertTrue($resolver->supports($request, $argument));
        $resolver->resolve($request, $argument);
    }

    private function requestWithAttributes(array $attributes)
    {
        $request = Request::create('/');

        foreach ($attributes as $name => $value) {
            $request->attributes->set($name, $value);
        }

        return $request;
    }
}
