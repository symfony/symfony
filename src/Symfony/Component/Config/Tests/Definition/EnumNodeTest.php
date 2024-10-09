<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Definition;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\EnumNode;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Tests\Fixtures\IntegerBackedTestEnum;
use Symfony\Component\Config\Tests\Fixtures\StringBackedTestEnum;
use Symfony\Component\Config\Tests\Fixtures\StringBackedTestEnum2;
use Symfony\Component\Config\Tests\Fixtures\TestEnum;
use Symfony\Component\Config\Tests\Fixtures\TestEnum2;

class EnumNodeTest extends TestCase
{
    public function testFinalizeValue()
    {
        $node = new EnumNode('foo', null, ['foo', 'bar', TestEnum::Bar]);
        $this->assertSame('foo', $node->finalize('foo'));
        $this->assertSame(TestEnum::Bar, $node->finalize(TestEnum::Bar));
    }

    public function testConstructionWithNoValues()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$values must contain at least one element.');
        new EnumNode('foo', null, []);
    }

    public function testConstructionWithBothValuesAndEnumFqcn()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$values or $enumFqcn cannot be both set.');
        new EnumNode('foo', null, [1, 2], enumFqcn: StringBackedTestEnum::class);
    }

    public function testConstructionWithInvlaidEnumFqcn()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "Symfony\Component\Config\Tests\Definition\InvalidEnum" enum does not exist.');
        new EnumNode('foo', null, enumFqcn: InvalidEnum::class);
    }

    public function testConstructionWithOneValue()
    {
        $node = new EnumNode('foo', null, ['foo']);
        $this->assertSame('foo', $node->finalize('foo'));
    }

    public function testConstructionWithOneDistinctValue()
    {
        $node = new EnumNode('foo', null, ['foo', 'foo']);
        $this->assertSame('foo', $node->finalize('foo'));
    }

    public function testConstructionWithNullName()
    {
        $node = new EnumNode(null, null, ['foo']);
        $this->assertSame('foo', $node->finalize('foo'));
    }

    public function testFinalizeWithInvalidValue()
    {
        $node = new EnumNode('foo', null, ['foo', 'bar', TestEnum::Foo]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value "foobar" is not allowed for path "foo". Permissible values: "foo", "bar", Symfony\Component\Config\Tests\Fixtures\TestEnum::Foo');

        $node->finalize('foobar');
    }

    public function testFinalizeUnitEnumFqcnWithInvalidValue()
    {
        $node = new EnumNode('foo', null, enumFqcn: TestEnum::class);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value "foobar" is not allowed for path "foo". Permissible values: Symfony\Component\Config\Tests\Fixtures\TestEnum::Foo, Symfony\Component\Config\Tests\Fixtures\TestEnum::Bar, Symfony\Component\Config\Tests\Fixtures\TestEnum::Ccc (cases of the "Symfony\Component\Config\Tests\Fixtures\TestEnum" enum)');

        $node->finalize('foobar');
    }

    public function testFinalizeWithStringEnumFqcn()
    {
        $node = new EnumNode('foo', null, enumFqcn: StringBackedTestEnum::class);

        $this->assertSame(StringBackedTestEnum::Foo, $node->finalize(StringBackedTestEnum::Foo));
    }

    public function testFinalizeWithIntegerEnumFqcn()
    {
        $node = new EnumNode('foo', null, enumFqcn: IntegerBackedTestEnum::class);

        $this->assertSame(IntegerBackedTestEnum::One, $node->finalize(IntegerBackedTestEnum::One));
    }

    public function testFinalizeWithUnitEnumFqcn()
    {
        $node = new EnumNode('foo', null, enumFqcn: TestEnum::class);

        $this->assertSame(TestEnum::Foo, $node->finalize(TestEnum::Foo));
    }

    public function testFinalizeAnotherEnumWithEnumFqcn()
    {
        $node = new EnumNode('foo', null, enumFqcn: StringBackedTestEnum::class);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value should be part of the "Symfony\Component\Config\Tests\Fixtures\StringBackedTestEnum" enum, got a value from the "Symfony\Component\Config\Tests\Fixtures\StringBackedTestEnum2" enum.');

        $node->finalize(StringBackedTestEnum2::Foo);
    }

    public function testFinalizeWithEnumFqcnWorksWithPlainString()
    {
        $node = new EnumNode('foo', null, enumFqcn: StringBackedTestEnum::class);

        $this->assertSame(StringBackedTestEnum::Foo, $node->finalize('foo'));
    }

    public function testFinalizeWithEnumFqcnWorksWithInteger()
    {
        $node = new EnumNode('foo', null, enumFqcn: IntegerBackedTestEnum::class);

        $this->assertSame(IntegerBackedTestEnum::One, $node->finalize(1));
    }

    public function testFinalizeWithStringEnumFqcnWithWrongCase()
    {
        $node = new EnumNode('foo', null, enumFqcn: StringBackedTestEnum::class);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value "qux" is not allowed for path "foo". Permissible values: foo, bar (cases of the "Symfony\Component\Config\Tests\Fixtures\StringBackedTestEnum" enum)');

        $node->finalize('qux');
    }

    public function testFinalizeWithStringEnumFqcnWithIntegerCase()
    {
        $node = new EnumNode('foo', null, enumFqcn: StringBackedTestEnum::class);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value 1 is not allowed for path "foo". Permissible values: foo, bar (cases of the "Symfony\Component\Config\Tests\Fixtures\StringBackedTestEnum" enum).');

        $node->finalize(1);
    }

    public function testFinalizeWithIntegerEnumFqcnWithWrongCase()
    {
        $node = new EnumNode('foo', null, enumFqcn: IntegerBackedTestEnum::class);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value 3 is not allowed for path "foo". Permissible values: 1, 2 (cases of the "Symfony\Component\Config\Tests\Fixtures\IntegerBackedTestEnum" enum).');

        $node->finalize(3);
    }

    public function testFinalizeWithIntegerEnumFqcnWithStringCase()
    {
        $node = new EnumNode('foo', null, enumFqcn: IntegerBackedTestEnum::class);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value could not be casted to a case of the "Symfony\Component\Config\Tests\Fixtures\IntegerBackedTestEnum" enum. Is the value the same type as the backing type of the enum?');

        $node->finalize('my string');
    }

    public function testFinalizeWithEnumFqcnWithWrongType()
    {
        $node = new EnumNode('foo', null, enumFqcn: StringBackedTestEnum::class);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value true is not allowed for path "foo". Permissible values: foo, bar');

        $node->finalize(true);
    }

    public function testWithPlaceHolderWithValidValue()
    {
        $node = new EnumNode('cookie_samesite', null, ['lax', 'strict', 'none']);
        EnumNode::setPlaceholder('custom', ['string' => 'lax']);
        $this->assertSame('custom', $node->finalize('custom'));
    }

    public function testWithPlaceHolderWithInvalidValue()
    {
        $node = new EnumNode('cookie_samesite', null, ['lax', 'strict', 'none']);
        EnumNode::setPlaceholder('custom', ['string' => 'foo']);
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The value "foo" is not allowed for path "cookie_samesite". Permissible values: "lax", "strict", "none"');
        $node->finalize('custom');
    }

    public function testSameStringCoercedValuesAreDifferent()
    {
        $node = new EnumNode('ccc', null, ['', false, null]);
        $this->assertSame('', $node->finalize(''));
        $this->assertFalse($node->finalize(false));
        $this->assertNull($node->finalize(null));
    }

    public function testNonScalarOrEnumOrNullValueThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"Symfony\Component\Config\Definition\EnumNode" only supports scalar, enum, or null values, "stdClass" given.');

        new EnumNode('ccc', null, [new \stdClass()]);
    }

    public function testTwoDifferentEnumsThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"Symfony\Component\Config\Definition\EnumNode" only supports one type of enum, "Symfony\Component\Config\Tests\Fixtures\TestEnum" and "Symfony\Component\Config\Tests\Fixtures\TestEnum2" passed.');

        new EnumNode('ccc', null, [...TestEnum::cases(), TestEnum2::Ccc]);
    }
}
