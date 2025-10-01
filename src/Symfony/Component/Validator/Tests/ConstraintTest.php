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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
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
use Symfony\Component\Validator\Tests\Fixtures\LegacyConstraintA;

class ConstraintTest extends TestCase
{
    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testSetProperties()
    {
        $constraint = new LegacyConstraintA([
            'property1' => 'foo',
            'property2' => 'bar',
        ]);

        $this->assertEquals('foo', $constraint->property1);
        $this->assertEquals('bar', $constraint->property2);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testSetNotExistingPropertyThrowsException()
    {
        $this->expectException(InvalidOptionsException::class);

        new LegacyConstraintA([
            'foo' => 'bar',
        ]);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testMagicPropertiesAreNotAllowed()
    {
        $constraint = new LegacyConstraintA();

        $this->expectException(InvalidOptionsException::class);

        $constraint->foo = 'bar';
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testInvalidAndRequiredOptionsPassed()
    {
        $this->expectException(InvalidOptionsException::class);

        new ConstraintC([
            'option1' => 'default',
            'foo' => 'bar',
        ]);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testSetDefaultProperty()
    {
        $constraint = new LegacyConstraintA('foo');

        $this->assertEquals('foo', $constraint->property2);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testSetDefaultPropertyDoctrineStyle()
    {
        $constraint = new LegacyConstraintA(['value' => 'foo']);

        $this->assertEquals('foo', $constraint->property2);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testSetDefaultPropertyDoctrineStylePlusOtherProperty()
    {
        $constraint = new LegacyConstraintA(['value' => 'foo', 'property1' => 'bar']);

        $this->assertEquals('foo', $constraint->property2);
        $this->assertEquals('bar', $constraint->property1);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testSetDefaultPropertyDoctrineStyleWhenDefaultPropertyIsNamedValue()
    {
        $constraint = new ConstraintWithValueAsDefault(['value' => 'foo']);

        $this->assertEquals('foo', $constraint->value);
        $this->assertNull($constraint->property);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testDontSetDefaultPropertyIfValuePropertyExists()
    {
        $constraint = new ConstraintWithValue(['value' => 'foo']);

        $this->assertEquals('foo', $constraint->value);
        $this->assertNull($constraint->property);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testSetUndefinedDefaultProperty()
    {
        $this->expectException(ConstraintDefinitionException::class);

        new ConstraintB('foo');
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testRequiredOptionsMustBeDefined()
    {
        $this->expectException(MissingOptionsException::class);

        new ConstraintC();
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testRequiredOptionsPassed()
    {
        $constraint = new ConstraintC(['option1' => 'default']);

        $this->assertSame('default', $constraint->option1);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testGroupsAreConvertedToArray()
    {
        $constraint = new LegacyConstraintA(['groups' => 'Foo']);

        $this->assertEquals(['Foo'], $constraint->groups);
    }

    public function testAddDefaultGroupAddsGroup()
    {
        $constraint = new ConstraintA(null, null, ['Default']);
        $constraint->addImplicitGroupName('Foo');
        $this->assertEquals(['Default', 'Foo'], $constraint->groups);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testAllowsSettingZeroRequiredPropertyValue()
    {
        $constraint = new LegacyConstraintA(0);
        $this->assertEquals(0, $constraint->property2);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testCanCreateConstraintWithNoDefaultOptionAndEmptyArray()
    {
        $constraint = new ConstraintB([]);

        $this->assertSame([Constraint::PROPERTY_CONSTRAINT, Constraint::CLASS_CONSTRAINT], $constraint->getTargets());
    }

    public function testGetTargetsCanBeString()
    {
        $constraint = new ClassConstraint();

        $this->assertEquals('class', $constraint->getTargets());
    }

    public function testGetTargetsCanBeArray()
    {
        $constraint = new ConstraintA();

        $this->assertEquals(['property', 'class'], $constraint->getTargets());
    }

    public function testSerialize()
    {
        $constraint = new ConstraintA('foo', 'bar');

        $restoredConstraint = unserialize(serialize($constraint));

        $this->assertEquals($constraint, $restoredConstraint);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testSerializeDoctrineStyle()
    {
        $constraint = new LegacyConstraintA([
            'property1' => 'foo',
            'property2' => 'bar',
        ]);

        $restoredConstraint = unserialize(serialize($constraint));

        $this->assertEquals($constraint, $restoredConstraint);
    }

    public function testSerializeInitializesGroupsOptionToDefault()
    {
        $constraint = new ConstraintA('foo', 'bar');

        $constraint = unserialize(serialize($constraint));

        $expected = new ConstraintA('foo', 'bar', ['Default']);

        $this->assertEquals($expected, $constraint);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testSerializeInitializesGroupsOptionToDefaultDoctrineStyle()
    {
        $constraint = new LegacyConstraintA([
            'property1' => 'foo',
            'property2' => 'bar',
        ]);

        $constraint = unserialize(serialize($constraint));

        $expected = new LegacyConstraintA([
            'property1' => 'foo',
            'property2' => 'bar',
            'groups' => 'Default',
        ]);

        $this->assertEquals($expected, $constraint);
    }

    public function testSerializeKeepsCustomGroups()
    {
        $constraint = new ConstraintA('foo', 'bar', ['MyGroup']);

        $constraint = unserialize(serialize($constraint));

        $this->assertSame(['MyGroup'], $constraint->groups);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testSerializeKeepsCustomGroupsDoctrineStyle()
    {
        $constraint = new LegacyConstraintA([
            'property1' => 'foo',
            'property2' => 'bar',
            'groups' => 'MyGroup',
        ]);

        $constraint = unserialize(serialize($constraint));

        $this->assertSame(['MyGroup'], $constraint->groups);
    }

    public function testGetErrorNameForUnknownCode()
    {
        $this->expectException(InvalidArgumentException::class);
        Constraint::getErrorName(1);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testOptionsAsDefaultOption()
    {
        $constraint = new LegacyConstraintA($options = ['value1']);

        $this->assertEquals($options, $constraint->property2);

        $constraint = new LegacyConstraintA($options = ['value1', 'property1' => 'value2']);

        $this->assertEquals($options, $constraint->property2);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testInvalidOptions()
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('The options "0", "5" do not exist in constraint "Symfony\Component\Validator\Tests\Fixtures\LegacyConstraintA".');
        new LegacyConstraintA(['property2' => 'foo', 'bar', 5 => 'baz']);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testOptionsWithInvalidInternalPointer()
    {
        $options = ['property1' => 'foo'];
        next($options);
        next($options);

        $constraint = new LegacyConstraintA($options);

        $this->assertEquals('foo', $constraint->property1);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testAttributeSetUndefinedDefaultOption()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('No default option is configured for constraint "Symfony\Component\Validator\Tests\Fixtures\ConstraintB".');
        new ConstraintB(['value' => 1]);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testStaticPropertiesAreNoOptions()
    {
        $this->expectException(InvalidOptionsException::class);

        new ConstraintWithStaticProperty([
            'foo' => 'bar',
        ]);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testSetTypedProperty()
    {
        $constraint = new ConstraintWithTypedProperty([
            'foo' => 'bar',
        ]);

        $this->assertSame('bar', $constraint->foo);
    }
}
