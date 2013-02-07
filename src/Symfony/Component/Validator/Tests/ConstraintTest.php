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

use Symfony\Component\Validator\Tests\Fixtures\ClassConstraint;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintB;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintC;

class ConstraintTest extends \PHPUnit_Framework_TestCase
{
    public function testSetProperties()
    {
        $constraint = new ConstraintA(array(
            'property1' => 'foo',
            'property2' => 'bar',
        ));

        $this->assertEquals('foo', $constraint->property1);
        $this->assertEquals('bar', $constraint->property2);
    }

    public function testSetNotExistingPropertyThrowsException()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\InvalidOptionsException');

        new ConstraintA(array(
            'foo' => 'bar',
        ));
    }

    public function testMagicPropertiesAreNotAllowed()
    {
        $constraint = new ConstraintA();

        $this->setExpectedException('Symfony\Component\Validator\Exception\InvalidOptionsException');

        $constraint->foo = 'bar';
    }

    public function testInvalidAndRequiredOptionsPassed()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\InvalidOptionsException');

        new ConstraintC(array(
            'option1' => 'default',
            'foo' => 'bar'
        ));
    }

    public function testSetDefaultProperty()
    {
        $constraint = new ConstraintA('foo');

        $this->assertEquals('foo', $constraint->property2);
    }

    public function testSetDefaultPropertyDoctrineStyle()
    {
        $constraint = new ConstraintA(array('value' => 'foo'));

        $this->assertEquals('foo', $constraint->property2);
    }

    public function testSetUndefinedDefaultProperty()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');

        new ConstraintB('foo');
    }

    public function testRequiredOptionsMustBeDefined()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\MissingOptionsException');

        new ConstraintC();
    }

    public function testRequiredOptionsPassed()
    {
        new ConstraintC(array('option1' => 'default'));
    }

    public function testGroupsAreConvertedToArray()
    {
        $constraint = new ConstraintA(array('groups' => 'Foo'));

        $this->assertEquals(array('Foo'), $constraint->groups);
    }

    public function testAddDefaultGroupAddsGroup()
    {
        $constraint = new ConstraintA(array('groups' => 'Default'));
        $constraint->addImplicitGroupName('Foo');
        $this->assertEquals(array('Default', 'Foo'), $constraint->groups);
    }

    public function testAllowsSettingZeroRequiredPropertyValue()
    {
        $constraint = new ConstraintA(0);
        $this->assertEquals(0, $constraint->property2);
    }

    public function testCanCreateConstraintWithNoDefaultOptionAndEmptyArray()
    {
        new ConstraintB(array());
    }

    public function testGetTargetsCanBeString()
    {
        $constraint = new ClassConstraint;

        $this->assertEquals('class', $constraint->getTargets());
    }

    public function testGetTargetsCanBeArray()
    {
        $constraint = new ConstraintA;

        $this->assertEquals(array('property', 'class'), $constraint->getTargets());
    }
}
