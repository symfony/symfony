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
use Symfony\Component\Console\ArgumentResolver\ValueResolver\BackedEnumValueResolver;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class BackedEnumValueResolverTest extends TestCase
{
    public function testResolveBackedEnumArgument()
    {
        $resolver = new BackedEnumValueResolver();

        $input = new ArrayInput(['status' => 'pending'], new InputDefinition([
            new InputArgument('status'),
        ]));

        $command = new class {
            public function __invoke(
                #[Argument]
                BackedEnumTestStatus $status,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = iterator_to_array($resolver->resolve('status', $input, $member));

        $this->assertSame([BackedEnumTestStatus::Pending], $result);
    }

    public function testResolveBackedEnumOption()
    {
        $resolver = new BackedEnumValueResolver();

        $input = new ArrayInput(['--status' => 'completed'], new InputDefinition([
            new InputOption('status'),
        ]));

        $command = new class {
            public function __invoke(
                #[Option]
                BackedEnumTestStatus $status = BackedEnumTestStatus::Pending,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = iterator_to_array($resolver->resolve('status', $input, $member));

        $this->assertSame([BackedEnumTestStatus::Completed], $result);
    }

    public function testResolveVariadicBackedEnumArgument()
    {
        $resolver = new BackedEnumValueResolver();

        $input = new ArrayInput(['statuses' => ['pending', 'completed']], new InputDefinition([
            new InputArgument('statuses', InputArgument::IS_ARRAY),
        ]));

        $command = new class {
            public function __invoke(
                #[Argument]
                BackedEnumTestStatus ...$statuses,
            ) {
            }
        };
        $member = new ReflectionMember((new \ReflectionMethod($command, '__invoke'))->getParameters()[0]);

        $this->assertSame([BackedEnumTestStatus::Pending, BackedEnumTestStatus::Completed], iterator_to_array($resolver->resolve('statuses', $input, $member)));
    }

    public function testResolveVariadicBackedEnumOption()
    {
        $resolver = new BackedEnumValueResolver();

        $input = new ArrayInput(['--statuses' => ['completed', 'pending']], new InputDefinition([
            new InputOption('statuses', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY),
        ]));

        $command = new class {
            public function __invoke(
                #[Option]
                BackedEnumTestStatus ...$statuses,
            ) {
            }
        };
        $member = new ReflectionMember((new \ReflectionMethod($command, '__invoke'))->getParameters()[0]);

        $this->assertSame([BackedEnumTestStatus::Completed, BackedEnumTestStatus::Pending], iterator_to_array($resolver->resolve('statuses', $input, $member)));
    }

    public function testVariadicBackedEnumOptionThrowsOnInvalidValue()
    {
        $resolver = new BackedEnumValueResolver();

        $input = new ArrayInput(['--statuses' => ['pending', 'invalid']], new InputDefinition([
            new InputOption('statuses', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY),
        ]));

        $command = new class {
            public function __invoke(
                #[Option]
                BackedEnumTestStatus ...$statuses,
            ) {
            }
        };
        $member = new ReflectionMember((new \ReflectionMethod($command, '__invoke'))->getParameters()[0]);

        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The value "invalid" is not valid for the "statuses" option.');

        iterator_to_array($resolver->resolve('statuses', $input, $member));
    }

    public function testBackedEnumArgumentThrowsOnInvalidValue()
    {
        $this->expectException(InvalidArgumentException::class);

        $resolver = new BackedEnumValueResolver();

        $input = new ArrayInput(['status' => 'invalid'], new InputDefinition([
            new InputArgument('status'),
        ]));

        $command = new class {
            public function __invoke(
                #[Argument]
                BackedEnumTestStatus $status,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        iterator_to_array($resolver->resolve('status', $input, $member));
    }

    public function testBackedEnumOptionThrowsOnInvalidValue()
    {
        $this->expectException(InvalidOptionException::class);

        $resolver = new BackedEnumValueResolver();

        $input = new ArrayInput(['--status' => 'invalid'], new InputDefinition([
            new InputOption('status'),
        ]));

        $command = new class {
            public function __invoke(
                #[Option]
                BackedEnumTestStatus $status = BackedEnumTestStatus::Pending,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        iterator_to_array($resolver->resolve('status', $input, $member));
    }

    public function testResolveBackedEnumListArgument()
    {
        $resolver = new BackedEnumValueResolver();

        $input = new ArrayInput(['statuses' => ['pending', 'completed']], new InputDefinition([
            new InputArgument('statuses', InputArgument::IS_ARRAY),
        ]));

        $command = new class {
            /** @param list<BackedEnumTestStatus> $statuses */
            public function __invoke(
                #[Argument]
                array $statuses = [],
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = iterator_to_array($resolver->resolve('statuses', $input, $member));

        $this->assertSame([[BackedEnumTestStatus::Pending, BackedEnumTestStatus::Completed]], $result);
    }

    public function testResolveBackedEnumListOption()
    {
        $resolver = new BackedEnumValueResolver();

        $input = new ArrayInput(['--statuses' => ['pending', 'completed']], new InputDefinition([
            new InputOption('statuses', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY),
        ]));

        $command = new class {
            /** @param list<BackedEnumTestStatus> $statuses */
            public function __invoke(
                #[Option]
                array $statuses = [],
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = iterator_to_array($resolver->resolve('statuses', $input, $member));

        $this->assertSame([[BackedEnumTestStatus::Pending, BackedEnumTestStatus::Completed]], $result);
    }

    public function testResolveNullableBackedEnumListOption()
    {
        $resolver = new BackedEnumValueResolver();

        $input = new ArrayInput([], new InputDefinition([
            new InputOption('statuses', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY),
        ]));

        $command = new class {
            /** @param list<BackedEnumTestStatus>|null $statuses */
            public function __invoke(
                #[Option]
                ?array $statuses = null,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = iterator_to_array($resolver->resolve('statuses', $input, $member));

        $this->assertSame([null], $result);
    }

    public function testBackedEnumListOptionThrowsOnInvalidValue()
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The value "invalid" is not valid for the "statuses" option. Supported values are "pending", "completed", "failed".');

        $resolver = new BackedEnumValueResolver();

        $input = new ArrayInput(['--statuses' => ['pending', 'invalid']], new InputDefinition([
            new InputOption('statuses', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY),
        ]));

        $command = new class {
            /** @param list<BackedEnumTestStatus> $statuses */
            public function __invoke(
                #[Option]
                array $statuses = [],
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        iterator_to_array($resolver->resolve('statuses', $input, $member));
    }

    public function testDoesNotResolveNonEnumArgument()
    {
        $resolver = new BackedEnumValueResolver();

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

        $result = iterator_to_array($resolver->resolve('username', $input, $member));

        $this->assertSame([], $result);
    }

    public function testDoesNotResolveNonEnumOption()
    {
        $resolver = new BackedEnumValueResolver();

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

        $result = iterator_to_array($resolver->resolve('name', $input, $member));

        $this->assertSame([], $result);
    }

    public function testDoesNotResolveWithoutAttribute()
    {
        $resolver = new BackedEnumValueResolver();

        $input = new ArrayInput(['status' => 'pending'], new InputDefinition([
            new InputArgument('status'),
        ]));

        $function = static fn (BackedEnumTestStatus $status) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = iterator_to_array($resolver->resolve('status', $input, $member));

        $this->assertSame([], $result);
    }

    public function testResolveIntBackedEnumArgument()
    {
        $resolver = new BackedEnumValueResolver();

        $input = new ArrayInput(['priority' => 1], new InputDefinition([
            new InputArgument('priority'),
        ]));

        $command = new class {
            public function __invoke(
                #[Argument]
                BackedEnumTestPriority $priority,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = iterator_to_array($resolver->resolve('priority', $input, $member));

        $this->assertSame([BackedEnumTestPriority::High], $result);
    }
}

enum BackedEnumTestStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}

enum BackedEnumTestPriority: int
{
    case Low = 0;
    case High = 1;
    case Critical = 2;
}
