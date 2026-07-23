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
use Symfony\Component\Console\ArgumentResolver\ValueResolver\UidValueResolver;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;

class UidValueResolverTest extends TestCase
{
    public function testResolveUuidArgument()
    {
        $resolver = new UidValueResolver();
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $input = new ArrayInput(['id' => $uuid], new InputDefinition([
            new InputArgument('id'),
        ]));

        $command = new class {
            public function __invoke(
                #[Argument]
                Uuid $id,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = iterator_to_array($resolver->resolve('id', $input, $member));

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Uuid::class, $result[0]);
        $this->assertSame($uuid, (string) $result[0]);
    }

    public function testResolveUlidArgument()
    {
        $resolver = new UidValueResolver();
        $ulid = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

        $input = new ArrayInput(['id' => $ulid], new InputDefinition([
            new InputArgument('id'),
        ]));

        $command = new class {
            public function __invoke(
                #[Argument]
                Ulid $id,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = iterator_to_array($resolver->resolve('id', $input, $member));

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Ulid::class, $result[0]);
        $this->assertSame($ulid, (string) $result[0]);
    }

    public function testResolveUuidOption()
    {
        $resolver = new UidValueResolver();
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $input = new ArrayInput(['--id' => $uuid], new InputDefinition([
            new InputOption('id', null, InputOption::VALUE_REQUIRED),
        ]));

        $command = new class {
            public function __invoke(
                #[Option]
                ?Uuid $id = null,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = iterator_to_array($resolver->resolve('id', $input, $member));

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Uuid::class, $result[0]);
        $this->assertSame($uuid, (string) $result[0]);
    }

    public function testArgumentThrowsOnInvalidUid()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The uid for the "id" argument is invalid.');

        $resolver = new UidValueResolver();

        $input = new ArrayInput(['id' => 'not-a-valid-uuid'], new InputDefinition([
            new InputArgument('id'),
        ]));

        $command = new class {
            public function __invoke(
                #[Argument]
                Uuid $id,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        iterator_to_array($resolver->resolve('id', $input, $member));
    }

    public function testOptionThrowsOnInvalidUid()
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The uid for the "--id" option is invalid.');

        $resolver = new UidValueResolver();

        $input = new ArrayInput(['--id' => 'not-a-valid-uuid'], new InputDefinition([
            new InputOption('id', null, InputOption::VALUE_REQUIRED),
        ]));

        $command = new class {
            public function __invoke(
                #[Option]
                ?Uuid $id = null,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        iterator_to_array($resolver->resolve('id', $input, $member));
    }

    public function testReturnsNullWhenArgumentIsNull()
    {
        $resolver = new UidValueResolver();

        $input = new ArrayInput(['id' => null], new InputDefinition([
            new InputArgument('id', InputArgument::OPTIONAL),
        ]));

        $command = new class {
            public function __invoke(
                #[Argument]
                ?Uuid $id = null,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = iterator_to_array($resolver->resolve('id', $input, $member));

        $this->assertSame([null], $result);
    }

    public function testDoesNotResolveNonUidType()
    {
        $resolver = new UidValueResolver();

        $input = new ArrayInput(['id' => '123'], new InputDefinition([
            new InputArgument('id'),
        ]));

        $command = new class {
            public function __invoke(
                #[Argument]
                string $id,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = iterator_to_array($resolver->resolve('id', $input, $member));

        $this->assertSame([], $result);
    }

    public function testDoesNotResolveWithoutAttribute()
    {
        $resolver = new UidValueResolver();

        $input = new ArrayInput(['id' => '550e8400-e29b-41d4-a716-446655440000'], new InputDefinition([
            new InputArgument('id'),
        ]));

        $function = static fn (Uuid $id) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = iterator_to_array($resolver->resolve('id', $input, $member));

        $this->assertSame([], $result);
    }

    public function testResolveSpecificUuidVersion()
    {
        $resolver = new UidValueResolver();
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $input = new ArrayInput(['id' => $uuid], new InputDefinition([
            new InputArgument('id'),
        ]));

        $command = new class {
            public function __invoke(
                #[Argument]
                UuidV4 $id,
            ) {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = iterator_to_array($resolver->resolve('id', $input, $member));

        $this->assertCount(1, $result);
        $this->assertInstanceOf(UuidV4::class, $result[0]);
    }
}
