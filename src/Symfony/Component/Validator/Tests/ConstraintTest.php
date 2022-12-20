<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Exception\InvalidOptionsException;
use Symfony\Component\Validator\Exception\MissingOptionsException;
use Symfony\Component\Validator\Tests\Fixtures\ClassConstraint;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintB;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintC;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintWithStaticProperty;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintWithTypedProperty;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintWithValue;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintWithValueAsDefault;

class ConstraintTest extends TestCase
{
    public function testSetProperties()
    {
        $constraint = new ConstraintA([
            'property1' => 'foo',
            'property2' => 'bar',
        ]);

        self::assertEquals('foo', $constraint->property1);
        self::assertEquals('bar', $constraint->property2);
    }

    public function testSetNotExistingPropertyThrowsException()
    {
        self::expectException(InvalidOptionsException::class);

        new ConstraintA([
            'foo' => 'bar',
        ]);
    }

    public function testMagicPropertiesAreNotAllowed()
    {
        $constraint = new ConstraintA();

        self::expectException(InvalidOptionsException::class);

        $constraint->foo = 'bar';
    }

    public function testInvalidAndRequiredOptionsPassed()
    {
        self::expectException(InvalidOptionsException::class);

        new ConstraintC([
            'option1' => 'default',
            'foo' => 'bar',
        ]);
    }

    public function testSetDefaultProperty()
    {
        $constraint = new ConstraintA('foo');

        self::assertEquals('foo', $constraint->property2);
    }

    public function testSetDefaultPropertyDoctrineStyle()
    {
        $constraint = new ConstraintA(['value' => 'foo']);

        self::assertEquals('foo', $constraint->property2);
    }

    public function testSetDefaultPropertyDoctrineStylePlusOtherProperty()
    {
        $constraint = new ConstraintA(['value' => 'foo', 'property1' => 'bar']);

        self::assertEquals('foo', $constraint->property2);
        self::assertEquals('bar', $constraint->property1);
    }

    public function testSetDefaultPropertyDoctrineStyleWhenDefaultPropertyIsNamedValue()
    {
        $constraint = new ConstraintWithValueAsDefault(['value' => 'foo']);

        self::assertEquals('foo', $constraint->value);
        self::assertNull($constraint->property);
    }

    public function testDontSetDefaultPropertyIfValuePropertyExists()
    {
        $constraint = new ConstraintWithValue(['value' => 'foo']);

        self::assertEquals('foo', $constraint->value);
        self::assertNull($constraint->property);
    }

    public function testSetUndefinedDefaultProperty()
    {
        self::expectException(ConstraintDefinitionException::class);

        new ConstraintB('foo');
    }

    public function testRequiredOptionsMustBeDefined()
    {
        self::expectException(MissingOptionsException::class);

        new ConstraintC();
    }

    public function testRequiredOptionsPassed()
    {
        $constraint = new ConstraintC(['option1' => 'default']);

        self::assertSame('default', $constraint->option1);
    }

    public function testGroupsAreConvertedToArray()
    {
        $constraint = new ConstraintA(['groups' => 'Foo']);

        self::assertEquals(['Foo'], $constraint->groups);
    }

    public function testAddDefaultGroupAddsGroup()
    {
        $constraint = new ConstraintA(['groups' => 'Default']);
        $constraint->addImplicitGroupName('Foo');
        self::assertEquals(['Default', 'Foo'], $constraint->groups);
    }

    public function testAllowsSettingZeroRequiredPropertyValue()
    {
        $constraint = new ConstraintA(0);
        self::assertEquals(0, $constraint->property2);
    }

    public function testCanCreateConstraintWithNoDefaultOptionAndEmptyArray()
    {
        $constraint = new ConstraintB([]);

        self::assertSame([Constraint::PROPERTY_CONSTRAINT, Constraint::CLASS_CONSTRAINT], $constraint->getTargets());
    }

    public function testGetTargetsCanBeString()
    {
        $constraint = new ClassConstraint();

        self::assertEquals('class', $constraint->getTargets());
    }

    public function testGetTargetsCanBeArray()
    {
        $constraint = new ConstraintA();

        self::assertEquals(['property', 'class'], $constraint->getTargets());
    }

    public function testSerialize()
    {
        $constraint = new ConstraintA([
            'property1' => 'foo',
            'property2' => 'bar',
        ]);

        $restoredConstraint = unserialize(serialize($constraint));

        self::assertEquals($constraint, $restoredConstraint);
    }

    public function testSerializeInitializesGroupsOptionToDefault()
    {
        $constraint = new ConstraintA([
            'property1' => 'foo',
            'property2' => 'bar',
        ]);

        $constraint = unserialize(serialize($constraint));

        $expected = new ConstraintA([
            'property1' => 'foo',
            'property2' => 'bar',
            'groups' => 'Default',
        ]);

        self::assertEquals($expected, $constraint);
    }

    public function testSerializeKeepsCustomGroups()
    {
        $constraint = new ConstraintA([
            'property1' => 'foo',
            'property2' => 'bar',
            'groups' => 'MyGroup',
        ]);

        $constraint = unserialize(serialize($constraint));

        self::assertSame(['MyGroup'], $constraint->groups);
    }

    public function testGetErrorNameForUnknownCode()
    {
        self::expectException(InvalidArgumentException::class);
        Constraint::getErrorName(1);
    }

    public function testOptionsAsDefaultOption()
    {
        $constraint = new ConstraintA($options = ['value1']);

        self::assertEquals($options, $constraint->property2);

        $constraint = new ConstraintA($options = ['value1', 'property1' => 'value2']);

        self::assertEquals($options, $constraint->property2);
    }

    public function testInvalidOptions()
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The options "0", "5" do not exist in constraint "Symfony\Component\Validator\Tests\Fixtures\ConstraintA".');
        new ConstraintA(['property2' => 'foo', 'bar', 5 => 'baz']);
    }

    public function testOptionsWithInvalidInternalPointer()
    {
        $options = ['property1' => 'foo'];
        next($options);
        next($options);

        $constraint = new ConstraintA($options);

        self::assertEquals('foo', $constraint->property1);
    }

    public function testAnnotationSetUndefinedDefaultOption()
    {
        self::expectException(ConstraintDefinitionException::class);
        self::expectExceptionMessage('No default option is configured for constraint "Symfony\Component\Validator\Tests\Fixtures\ConstraintB".');
        new ConstraintB(['value' => 1]);
    }

    public function testStaticPropertiesAreNoOptions()
    {
        self::expectException(InvalidOptionsException::class);

        new ConstraintWithStaticProperty([
            'foo' => 'bar',
        ]);
    }

    /**
     * @requires PHP 7.4
     */
    public function testSetTypedProperty()
    {
        $constraint = new ConstraintWithTypedProperty([
            'foo' => 'bar',
        ]);

        self::assertSame('bar', $constraint->foo);
    }
}
