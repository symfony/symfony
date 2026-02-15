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
use Symfony\Component\Console\ArgumentResolver\ValueResolver\BuiltinTypeValueResolver;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class BuiltinTypeValueResolverTest extends TestCase
{
    public function testResolveStringArgument()
    {
        $resolver = new BuiltinTypeValueResolver();

        $input = new ArrayInput(['username' => 'john'], new InputDefinition([
            new InputArgument('username'),
        ]));

        $command = new class {
            public function __invoke(
                #[Argument]
                string $username,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('username', $input, $member);

        $this->assertSame(['john'], $result);
    }

    public function testResolveStringOption()
    {
        $resolver = new BuiltinTypeValueResolver();

        $input = new ArrayInput(['--name' => 'john'], new InputDefinition([
            new InputOption('name'),
        ]));

        $command = new class {
            public function __invoke(
                #[Option]
                string $name = '',
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('name', $input, $member);

        $this->assertSame(['john'], $result);
    }

    public function testDelegatesToBackedEnumValueResolverForEnumArgument()
    {
        $resolver = new BuiltinTypeValueResolver();

        $input = new ArrayInput(['status' => 'pending'], new InputDefinition([
            new InputArgument('status'),
        ]));

        $command = new class {
            public function __invoke(
                #[Argument]
                DummyBackedEnum $status,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('status', $input, $member);

        // BuiltinTypeValueResolver returns empty for enums - BackedEnumValueResolver handles them
        $this->assertSame([], $result);
    }

    public function testDelegatesToBackedEnumValueResolverForEnumOption()
    {
        $resolver = new BuiltinTypeValueResolver();

        $input = new ArrayInput(['--status' => 'completed'], new InputDefinition([
            new InputOption('status'),
        ]));

        $command = new class {
            public function __invoke(
                #[Option]
                DummyBackedEnum $status = DummyBackedEnum::Pending,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('status', $input, $member);

        // BuiltinTypeValueResolver returns empty for enums - BackedEnumValueResolver handles them
        $this->assertSame([], $result);
    }

    public function testResolveBoolOption()
    {
        $resolver = new BuiltinTypeValueResolver();

        $input = new ArrayInput(['--force' => true], new InputDefinition([
            new InputOption('force'),
        ]));

        $command = new class {
            public function __invoke(
                #[Option]
                bool $force = false,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('force', $input, $member);

        $this->assertSame([true], $result);
    }

    public function testResolveNullableBoolOptionWithNullValue()
    {
        $resolver = new BuiltinTypeValueResolver();

        $input = new ArrayInput([], new InputDefinition([
            new InputOption('force'),
        ]));

        $command = new class {
            public function __invoke(
                #[Option]
                ?bool $force = null,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('force', $input, $member);

        $this->assertSame([false], $result);
    }

    public function testResolveArrayOption()
    {
        $resolver = new BuiltinTypeValueResolver();

        $input = new ArrayInput(['--tags' => ['foo', 'bar']], new InputDefinition([
            new InputOption('tags', mode: InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED),
        ]));

        $command = new class {
            public function __invoke(
                #[Option]
                array $tags = [],
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('tags', $input, $member);

        $this->assertSame([['foo', 'bar']], $result);
    }

    public function testResolveNullableArrayOptionWithEmptyValue()
    {
        $resolver = new BuiltinTypeValueResolver();

        $input = new ArrayInput([], new InputDefinition([
            new InputOption('tags', mode: InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL),
        ]));

        $command = new class {
            public function __invoke(
                #[Option]
                ?array $tags = null,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('tags', $input, $member);

        $this->assertSame([null], $result);
    }

    public function testDoesNotResolveWithoutAttribute()
    {
        $resolver = new BuiltinTypeValueResolver();

        $input = new ArrayInput(['username' => 'john'], new InputDefinition([
            new InputArgument('username'),
        ]));

        $function = static fn (string $username) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('username', $input, $member);

        $this->assertSame([], $result);
    }

    public function testResolveIntegerArgument()
    {
        $resolver = new BuiltinTypeValueResolver();

        $input = new ArrayInput(['count' => 42], new InputDefinition([
            new InputArgument('count'),
        ]));

        $command = new class {
            public function __invoke(
                #[Argument]
                int $count,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('count', $input, $member);

        $this->assertSame([42], $result);
    }
}

enum DummyBackedEnum: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
