<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Argument;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\LazyProxyArgument;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

class LazyProxyArgumentTest extends TestCase
{
    #[TestWith([[], []], 'none')]
    #[TestWith(['SomeInterface', ['SomeInterface']])]
    #[TestWith([['SomeInterface'], ['SomeInterface']])]
    #[TestWith([['SomeInterface', 'AnotherInterface'], ['SomeInterface', 'AnotherInterface']])]
    public function testGetValues(string|array $interfaces, array $expectedInterfaces)
    {
        $argument = new LazyProxyArgument($reference = new Reference('a'), $interfaces);

        self::assertSame([$reference, $expectedInterfaces, null], $argument->getValues());
    }

    public function testGetValuesDefaultsToNoInterface()
    {
        $argument = new LazyProxyArgument($reference = new Reference('a'));

        self::assertSame([$reference, [], null], $argument->getValues());
    }

    public function testSetValues()
    {
        $argument = new LazyProxyArgument(new Reference('a'));
        $argument->setValues([$reference = new Reference('b'), 'SomeInterface']);

        self::assertSame([$reference, ['SomeInterface'], null], $argument->getValues());
    }

    public function testSetValuesKeepsInterfacesWhenNotProvided()
    {
        $argument = new LazyProxyArgument(new Reference('a'), ['SomeInterface']);
        $argument->setValues([$reference = new Reference('b')]);

        self::assertSame([$reference, ['SomeInterface'], null], $argument->getValues());
    }

    public function testSetValuesWithResolvedReference()
    {
        $argument = new LazyProxyArgument($reference = new Reference('a'), ['SomeInterface']);
        $argument->setValues([$reference, ['SomeInterface'], $resolvedReference = new Reference('.lazy.a')]);

        self::assertSame([$reference, ['SomeInterface'], $resolvedReference], $argument->getValues());
    }

    #[TestWith([''], 'empty string')]
    #[TestWith([['']], 'array holding an empty string')]
    #[TestWith([[null]], 'array holding null')]
    #[TestWith([[123]], 'array holding an integer')]
    #[TestWith([[true]], 'array holding a boolean')]
    #[TestWith([['SomeInterface', '']], 'array holding one valid and one empty string')]
    public function testInvalidInterfacesThrows(string|array $interfaces)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A lazy proxy argument expects $interfaces to be a non-empty string or an array of non-empty strings.');

        new LazyProxyArgument(new Reference('a'), $interfaces);
    }
}
