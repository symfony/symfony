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
use Symfony\Component\Console\ArgumentResolver\ValueResolver\DefaultValueResolver;
use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Input\ArrayInput;

class DefaultValueResolverTest extends TestCase
{
    public function testResolveParameterWithDefaultValue()
    {
        $resolver = new DefaultValueResolver();
        $input = new ArrayInput([]);

        $function = static fn (string $name = 'default') => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('name', $input, $member);

        $this->assertSame(['default'], $result);
    }

    public function testResolveNullableParameterWithoutDefaultValue()
    {
        $resolver = new DefaultValueResolver();
        $input = new ArrayInput([]);

        $function = static fn (?string $name) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('name', $input, $member);

        $this->assertSame([null], $result);
    }

    public function testResolveVariadicParameterReturnsEmpty()
    {
        $resolver = new DefaultValueResolver();
        $input = new ArrayInput([]);

        $function = static fn (string ...$names) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('names', $input, $member);

        $this->assertSame([], $result);
    }

    public function testResolveRequiredParameterWithoutDefaultReturnsEmpty()
    {
        $resolver = new DefaultValueResolver();
        $input = new ArrayInput([]);

        $function = static fn (string $name) => null;
        $reflection = new \ReflectionFunction($function);
        $parameter = $reflection->getParameters()[0];
        $member = new ReflectionMember($parameter);

        $result = $resolver->resolve('name', $input, $member);

        $this->assertSame([], $result);
    }
}
