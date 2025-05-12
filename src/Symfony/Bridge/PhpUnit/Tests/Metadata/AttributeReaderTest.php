<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests\Metadata;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\Attribute\DnsSensitive;
use Symfony\Bridge\PhpUnit\Attribute\TimeSensitive;
use Symfony\Bridge\PhpUnit\Metadata\AttributeReader;
use Symfony\Bridge\PhpUnit\Tests\Metadata\Fixtures\FooBar;

/**
 * @requires PHP 8.0
 */
final class AttributeReaderTest extends TestCase
{
    /**
     * @dataProvider provideReadCases
     */
    public function testAttributesAreRead(string $method, string $attributeClass, array $expected)
    {
        $reader = new AttributeReader();

        $attributes = $reader->forClassAndMethod(FooBar::class, $method, $attributeClass);

        self::assertContainsOnlyInstancesOf($attributeClass, $attributes);
        self::assertSame($expected, array_column($attributes, 'class'));
    }

    public static function provideReadCases(): iterable
    {
        yield ['testOne', DnsSensitive::class, [
            'App\Foo\Bar\A',
            'App\Foo\Bar\B',
            'App\Foo\Baz\C',
        ]];
        yield ['testTwo', DnsSensitive::class, [
            'App\Foo\Bar\A',
            'App\Foo\Bar\B',
        ]];
        yield ['testThree', DnsSensitive::class, [
            'App\Foo\Bar\A',
            'App\Foo\Bar\B',
            'App\Foo\Corge\F',
        ]];

        yield ['testOne', TimeSensitive::class, [
            'App\Foo\Bar\A',
        ]];
        yield ['testTwo', TimeSensitive::class, [
            'App\Foo\Bar\A',
            'App\Foo\Qux\D',
            'App\Foo\Qux\E',
        ]];
        yield ['testThree', TimeSensitive::class, [
            'App\Foo\Bar\A',
            'App\Foo\Corge\G',
        ]];
    }

    public function testAttributesAreCached()
    {
        $reader = new AttributeReader();
        $cacheRef = new \ReflectionProperty(AttributeReader::class, 'cache');

        self::assertSame([], $cacheRef->getValue($reader));

        $reader->forClass(FooBar::class, TimeSensitive::class);

        self::assertCount(1, $cache = $cacheRef->getValue($reader));
        self::assertArrayHasKey(FooBar::class, $cache);
        self::assertAttributesCount($cache[FooBar::class], 2, 1);

        $reader->forMethod(FooBar::class, 'testThree', DnsSensitive::class);

        self::assertCount(2, $cache = $cacheRef->getValue($reader));
        self::assertArrayHasKey($key = FooBar::class.'::testThree', $cache);
        self::assertAttributesCount($cache[$key], 1, 1);
    }

    private static function assertAttributesCount(array $attributes, int $expectedDnsCount, int $expectedTimeCount): void
    {
        self::assertArrayHasKey(DnsSensitive::class, $attributes);
        self::assertCount($expectedDnsCount, $attributes[DnsSensitive::class]);
        self::assertArrayHasKey(TimeSensitive::class, $attributes);
        self::assertCount($expectedTimeCount, $attributes[TimeSensitive::class]);
    }
}
