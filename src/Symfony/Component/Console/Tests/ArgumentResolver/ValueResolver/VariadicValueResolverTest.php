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
use Symfony\Component\Console\ArgumentResolver\ValueResolver\VariadicValueResolver;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class VariadicValueResolverTest extends TestCase
{
    public function testResolveVariadicArgument(): void
    {
        $resolver = new VariadicValueResolver();

        $input = new ArrayInput(['files' => ['file1.txt', 'file2.txt', 'file3.txt']], new InputDefinition([
            new InputArgument('files', InputArgument::IS_ARRAY),
        ]));

        $command = new class {
            public function __invoke(
                #[Argument]
                string ...$files,
            ): void {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = iterator_to_array($resolver->resolve('files', $input, $member));

        $this->assertSame(['file1.txt', 'file2.txt', 'file3.txt'], $result);
    }

    public function testResolveVariadicOption(): void
    {
        $resolver = new VariadicValueResolver();

        $input = new ArrayInput(['--tags' => ['foo', 'bar', 'baz']], new InputDefinition([
            new InputOption('tags', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED),
        ]));

        $command = new class {
            public function __invoke(
                #[Option]
                string ...$tags,
            ): void {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = iterator_to_array($resolver->resolve('tags', $input, $member));

        $this->assertSame(['foo', 'bar', 'baz'], $result);
    }

    public function testResolveEmptyVariadicArgument(): void
    {
        $resolver = new VariadicValueResolver();

        $input = new ArrayInput(['files' => []], new InputDefinition([
            new InputArgument('files', InputArgument::IS_ARRAY | InputArgument::OPTIONAL),
        ]));

        $command = new class {
            public function __invoke(
                #[Argument]
                string ...$files,
            ): void {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = iterator_to_array($resolver->resolve('files', $input, $member));

        $this->assertSame([], $result);
    }

    public function testDoesNotResolveNonVariadicParameter(): void
    {
        $resolver = new VariadicValueResolver();

        $input = new ArrayInput(['name' => 'john'], new InputDefinition([
            new InputArgument('name'),
        ]));

        $command = new class {
            public function __invoke(
                #[Argument]
                string $name,
            ): void {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = iterator_to_array($resolver->resolve('name', $input, $member));

        $this->assertSame([], $result);
    }

    public function testDoesNotResolveWithoutAttribute(): void
    {
        $resolver = new VariadicValueResolver();

        $input = new ArrayInput(['files' => ['file1.txt', 'file2.txt']], new InputDefinition([
            new InputArgument('files', InputArgument::IS_ARRAY),
        ]));

        $function = static fn (string ...$files) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = iterator_to_array($resolver->resolve('files', $input, $member));

        $this->assertSame([], $result);
    }

    public function testThrowsWhenArgumentValueIsNotArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The action argument "...$files" is required to be an array');

        $resolver = new VariadicValueResolver();

        $input = new ArrayInput(['files' => 'single-value'], new InputDefinition([
            new InputArgument('files'),
        ]));

        $command = new class {
            public function __invoke(
                #[Argument]
                string ...$files,
            ): void {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        iterator_to_array($resolver->resolve('files', $input, $member));
    }

    public function testThrowsWhenOptionValueIsNotArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The action argument "...$tags" is required to be an array');

        $resolver = new VariadicValueResolver();

        $input = new ArrayInput(['--tags' => 'single-value'], new InputDefinition([
            new InputOption('tags', null, InputOption::VALUE_REQUIRED),
        ]));

        $command = new class {
            public function __invoke(
                #[Option]
                string ...$tags,
            ): void {
            }
        };
        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        iterator_to_array($resolver->resolve('tags', $input, $member));
    }
}
