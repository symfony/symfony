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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ServiceValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\DependencyInjection\RegisterControllerArgumentLocatorsPass;
use Symfony\Component\HttpKernel\Exception\NearMissValueResolverException;

class ServiceValueResolverTest extends TestCase
{
    public function testDoNotSupportWhenControllerDoNotExists()
    {
        $resolver = new ServiceValueResolver(new ServiceLocator([]));
        $argument = new ArgumentMetadata('dummy', DummyService::class, false, false, null);
        $request = $this->requestWithAttributes(['_controller' => 'my_controller']);

        $this->assertSame([], $resolver->resolve($request, $argument));
    }

    public function testExistingController()
    {
        $resolver = new ServiceValueResolver(new ServiceLocator([
            'App\\Controller\\Mine::method' => fn () => new ServiceLocator([
                'dummy' => fn () => new DummyService(),
            ]),
        ]));

        $request = $this->requestWithAttributes(['_controller' => 'App\\Controller\\Mine::method']);
        $argument = new ArgumentMetadata('dummy', DummyService::class, false, false, null);

        $this->assertEquals([new DummyService()], $resolver->resolve($request, $argument));
    }

    public function testExistingControllerWithATrailingBackSlash()
    {
        $resolver = new ServiceValueResolver(new ServiceLocator([
            'App\\Controller\\Mine::method' => fn () => new ServiceLocator([
                'dummy' => fn () => new DummyService(),
            ]),
        ]));

        $request = $this->requestWithAttributes(['_controller' => '\\App\\Controller\\Mine::method']);
        $argument = new ArgumentMetadata('dummy', DummyService::class, false, false, null);

        $this->assertEquals([new DummyService()], $resolver->resolve($request, $argument));
    }

    public function testExistingControllerWithMethodNameStartUppercase()
    {
        $resolver = new ServiceValueResolver(new ServiceLocator([
            'App\\Controller\\Mine::method' => fn () => new ServiceLocator([
                'dummy' => fn () => new DummyService(),
            ]),
        ]));
        $request = $this->requestWithAttributes(['_controller' => 'App\\Controller\\Mine::Method']);
        $argument = new ArgumentMetadata('dummy', DummyService::class, false, false, null);

        $this->assertEquals([new DummyService()], $resolver->resolve($request, $argument));
    }

    public function testControllerNameIsAnArray()
    {
        $resolver = new ServiceValueResolver(new ServiceLocator([
            'App\\Controller\\Mine::method' => fn () => new ServiceLocator([
                'dummy' => fn () => new DummyService(),
            ]),
        ]));

        $request = $this->requestWithAttributes(['_controller' => ['App\\Controller\\Mine', 'method']]);
        $argument = new ArgumentMetadata('dummy', DummyService::class, false, false, null);

        $this->assertEquals([new DummyService()], $resolver->resolve($request, $argument));
    }

    public function testErrorIsTruncated()
    {
        $this->expectException(NearMissValueResolverException::class);
        $this->expectExceptionMessage('Cannot autowire argument $dummy required by "Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver\DummyController::index()": it references class "Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver\DummyService" but no such service exists.');
        $container = new ContainerBuilder();
        $container->addCompilerPass(new RegisterControllerArgumentLocatorsPass());

        $container->register('argument_resolver.service', ServiceValueResolver::class)->addArgument(null)->setPublic(true);
        $container->register(DummyController::class)->addTag('controller.service_arguments')->setPublic(true);

        $container->compile();

        $request = $this->requestWithAttributes(['_controller' => [DummyController::class, 'index']]);
        $argument = new ArgumentMetadata('dummy', DummyService::class, false, false, null);
        $container->get('argument_resolver.service')->resolve($request, $argument)->current();
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

class DummyService
{
}

class DummyController
{
    public function index(DummyService $dummy)
    {
    }
}
