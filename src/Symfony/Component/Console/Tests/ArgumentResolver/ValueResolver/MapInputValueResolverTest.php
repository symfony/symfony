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
use Symfony\Component\Console\ArgumentResolver\ValueResolver\BuiltinTypeValueResolver;
use Symfony\Component\Console\ArgumentResolver\ValueResolver\DateTimeValueResolver;
use Symfony\Component\Console\ArgumentResolver\ValueResolver\MapInputValueResolver;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class MapInputValueResolverTest extends TestCase
{
    public function testResolveMapInput()
    {
        $resolver = new MapInputValueResolver(new BuiltinTypeValueResolver(), new BackedEnumValueResolver(), new DateTimeValueResolver());

        $input = new ArrayInput(['username' => 'john', '--email' => 'john@example.com'], new InputDefinition([
            new InputArgument('username'),
            new InputOption('email'),
        ]));

        $command = new class {
            public function __invoke(
                #[MapInput]
                DummyInput $input,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('input', $input, $member);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(DummyInput::class, $result[0]);
        $this->assertSame('john', $result[0]->username);
        $this->assertSame('john@example.com', $result[0]->email);
    }

    public function testDoesNotResolveWithoutAttribute()
    {
        $resolver = new MapInputValueResolver(new BuiltinTypeValueResolver(), new BackedEnumValueResolver(), new DateTimeValueResolver());

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

    public function testDoesNotResolveBuiltinTypes()
    {
        $resolver = new MapInputValueResolver(new BuiltinTypeValueResolver(), new BackedEnumValueResolver(), new DateTimeValueResolver());

        $input = new ArrayInput(['count' => '5'], new InputDefinition([
            new InputArgument('count'),
        ]));

        $function = static fn (int $count) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('count', $input, $member);

        $this->assertSame([], $result);
    }

    public function testResolvesDateTimeAndBackedEnum()
    {
        $resolver = new MapInputValueResolver(new BuiltinTypeValueResolver(), new BackedEnumValueResolver(), new DateTimeValueResolver());

        $input = new ArrayInput([
            'created-at' => '2024-01-15',
            '--status' => 'active',
        ], new InputDefinition([
            new InputArgument('created-at'),
            new InputOption('status'),
        ]));

        $command = new class {
            public function __invoke(
                #[MapInput]
                DummyInputWithDateTimeAndEnum $input,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('input', $input, $member);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(DummyInputWithDateTimeAndEnum::class, $result[0]);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result[0]->createdAt);
        $this->assertSame('2024-01-15', $result[0]->createdAt->format('Y-m-d'));
        $this->assertSame(DummyStatus::Active, $result[0]->status);
    }
}

class DummyInput
{
    #[Argument]
    public string $username;

    #[Option]
    public ?string $email = null;
}

class DummyInputWithDateTimeAndEnum
{
    #[Argument]
    public \DateTimeImmutable $createdAt;

    #[Option]
    public DummyStatus $status = DummyStatus::Pending;
}

enum DummyStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Inactive = 'inactive';
}
