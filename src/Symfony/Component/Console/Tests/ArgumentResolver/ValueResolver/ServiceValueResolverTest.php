<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\ArgumentResolver\ValueResolver;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\ArgumentResolver\Exception\NearMissValueResolverException;
use Symfony\Component\Console\ArgumentResolver\ValueResolver\ServiceValueResolver;
use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ServiceValueResolverTest extends TestCase
{
    public function testDoNotSupportWhenCommandDoesNotExist(): void
    {
        $resolver = new ServiceValueResolver(new ServiceLocator([]));
        $input = new ArrayInput(['app:test'], new InputDefinition([
            new InputArgument('command'),
        ]));

        $function = static fn (DummyService $dummy) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $this->assertSame([], $resolver->resolve('dummy', $input, $member));
    }

    public function testExistingCommand(): void
    {
        $resolver = new ServiceValueResolver(new ServiceLocator([
            'app:test' => static fn () => new ServiceLocator([
                'dummy' => static fn () => new DummyService(),
            ]),
        ]));

        $input = new ArrayInput(['app:test'], new InputDefinition([
            new InputArgument('command'),
        ]));

        $function = static fn (DummyService $dummy) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('dummy', $input, $member);

        $this->assertEquals([new DummyService()], $result);
    }

    public function testServiceLocatorPatternTakesPriorityOverTypeResolution(): void
    {
        $serviceA = new DummyService();
        $serviceB = new DummyService();

        $resolver = new ServiceValueResolver(new ServiceLocator([
            'app:test' => static fn () => new ServiceLocator([
                'dummy' => static fn () => $serviceA,
            ]),
            DummyService::class => static fn () => $serviceB,
        ]));

        $input = new ArrayInput(['app:test'], new InputDefinition([
            new InputArgument('command'),
        ]));

        $function = static fn (DummyService $dummy) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('dummy', $input, $member);

        $this->assertSame([$serviceA], $result);
    }

    public function testFallbackToTypeBasedResolution(): void
    {
        $service = new DummyService();

        $resolver = new ServiceValueResolver(new ServiceLocator([
            DummyService::class => static fn () => $service,
        ]));

        $input = new ArrayInput(['app:test'], new InputDefinition([
            new InputArgument('command'),
        ]));

        $function = static fn (DummyService $dummy) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('dummy', $input, $member);

        $this->assertSame([$service], $result);
    }

    public function testTypeResolutionReturnsEmptyForBuiltinTypes(): void
    {
        $resolver = new ServiceValueResolver(new ServiceLocator([]));

        $input = new ArrayInput(['app:test'], new InputDefinition([
            new InputArgument('command'),
        ]));

        $function = static fn (string $name) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('name', $input, $member);

        $this->assertSame([], $result);
    }

    public function testTypeResolutionReturnsEmptyWhenServiceDoesNotExist(): void
    {
        $resolver = new ServiceValueResolver(new ServiceLocator([]));

        $input = new ArrayInput(['app:test'], new InputDefinition([
            new InputArgument('command'),
        ]));

        $function = static fn (DummyService $dummy) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('dummy', $input, $member);

        $this->assertSame([], $result);
    }

    public function testThrowsNearMissExceptionWhenServiceExistsButWrongType(): void
    {
        $this->expectException(NearMissValueResolverException::class);
        $this->expectExceptionMessage('Service "Symfony\Component\Console\Tests\ArgumentResolver\ValueResolver\DummyService" exists in the container but is not an instance of "Symfony\Component\Console\Tests\ArgumentResolver\ValueResolver\DummyService".');

        $resolver = new ServiceValueResolver(new ServiceLocator([
            DummyService::class => static fn () => new \stdClass(),
        ]));

        $input = new ArrayInput(['app:test'], new InputDefinition([
            new InputArgument('command'),
        ]));

        $function = static fn (DummyService $dummy) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        iterator_to_array($resolver->resolve('dummy', $input, $member));
    }

    public function testThrowsNearMissExceptionOnServiceLocatorError(): void
    {
        $this->expectException(NearMissValueResolverException::class);

        $resolver = new ServiceValueResolver(new ServiceLocator([
            'app:test' => static fn () => new ServiceLocator([
                'dummy' => static fn () => throw new \RuntimeException('Service initialization failed'),
            ]),
        ]));

        $input = new ArrayInput(['app:test'], new InputDefinition([
            new InputArgument('command'),
        ]));

        $function = static fn (DummyService $dummy) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        iterator_to_array($resolver->resolve('dummy', $input, $member));
    }

    public function testDoesNotResolveWhenNoCommandArgument(): void
    {
        $resolver = new ServiceValueResolver(new ServiceLocator([
            'app:test' => static fn () => new ServiceLocator([
                'dummy' => static fn () => new DummyService(),
            ]),
        ]));

        $input = new ArrayInput([], new InputDefinition([]));

        $function = static fn (DummyService $dummy) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('dummy', $input, $member);

        $this->assertSame([], $result);
    }
}

class DummyService
{
}
