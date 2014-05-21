<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests;

use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClass;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClassMagicCall;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClassMagicGet;

class PropertyAccessorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PropertyAccessor
     */
    private $propertyAccessor;

    protected function setUp()
    {
        $this->propertyAccessor = new PropertyAccessor();
    }

    public function getValidPropertyPaths()
    {
        return array(
            array(array('Bernhard', 'Schussek'), '[0]', 'Bernhard'),
            array(array('Bernhard', 'Schussek'), '[1]', 'Schussek'),
            array(array('firstName' => 'Bernhard'), '[firstName]', 'Bernhard'),
            array(array('index' => array('firstName' => 'Bernhard')), '[index][firstName]', 'Bernhard'),
            array((object) array('firstName' => 'Bernhard'), 'firstName', 'Bernhard'),
            array((object) array('property' => array('firstName' => 'Bernhard')), 'property[firstName]', 'Bernhard'),
            array(array('index' => (object) array('firstName' => 'Bernhard')), '[index].firstName', 'Bernhard'),
            array((object) array('property' => (object) array('firstName' => 'Bernhard')), 'property.firstName', 'Bernhard'),

            // Accessor methods
            array(new TestClass('Bernhard'), 'publicProperty', 'Bernhard'),
            array(new TestClass('Bernhard'), 'publicAccessor', 'Bernhard'),
            array(new TestClass('Bernhard'), 'publicIsAccessor', 'Bernhard'),
            array(new TestClass('Bernhard'), 'publicHasAccessor', 'Bernhard'),

            // Methods are camelized
            array(new TestClass('Bernhard'), 'public_accessor', 'Bernhard'),

            // Missing indices
            array(array('index' => array()), '[index][firstName]', null),
            array(array('root' => array('index' => array())), '[root][index][firstName]', null),

            // Special chars
            array(array('%!@$§.' => 'Bernhard'), '[%!@$§.]', 'Bernhard'),
            array(array('index' => array('%!@$§.' => 'Bernhard')), '[index][%!@$§.]', 'Bernhard'),
            array((object) array('%!@$§' => 'Bernhard'), '%!@$§', 'Bernhard'),
        );
    }

    public function getPathsWithMissingProperty()
    {
        return array(
            array((object) array('firstName' => 'Bernhard'), 'lastName'),
            array((object) array('property' => (object) array('firstName' => 'Bernhard')), 'property.lastName'),
            array(array('index' => (object) array('firstName' => 'Bernhard')), '[index].lastName'),
            array(new TestClass('Bernhard'), 'protectedProperty'),
            array(new TestClass('Bernhard'), 'privateProperty'),
            array(new TestClass('Bernhard'), 'protectedAccessor'),
            array(new TestClass('Bernhard'), 'protectedIsAccessor'),
            array(new TestClass('Bernhard'), 'protectedHasAccessor'),
            array(new TestClass('Bernhard'), 'privateAccessor'),
            array(new TestClass('Bernhard'), 'privateIsAccessor'),
            array(new TestClass('Bernhard'), 'privateHasAccessor'),

            // Properties are not camelized
            array(new TestClass('Bernhard'), 'public_property'),
        );
    }

    public function getPathsWithMissingIndex()
    {
        return array(
            array(array('firstName' => 'Bernhard'), '[lastName]'),
            array(array(), '[index][lastName]'),
            array(array('index' => array()), '[index][lastName]'),
            array(array('index' => array('firstName' => 'Bernhard')), '[index][lastName]'),
            array((object) array('property' => array('firstName' => 'Bernhard')), 'property[lastName]'),
        );
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testGetValue($objectOrArray, $path, $value)
    {
        $this->assertSame($value, $this->propertyAccessor->getValue($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingProperty
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testGetValueThrowsExceptionIfPropertyNotFound($objectOrArray, $path)
    {
        $this->propertyAccessor->getValue($objectOrArray, $path);
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testGetValueThrowsNoExceptionIfIndexNotFound($objectOrArray, $path)
    {
        $this->assertNull($this->propertyAccessor->getValue($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchIndexException
     */
    public function testGetValueThrowsExceptionIfIndexNotFoundAndIndexExceptionsEnabled($objectOrArray, $path)
    {
        $this->propertyAccessor = new PropertyAccessor(false, true);
        $this->propertyAccessor->getValue($objectOrArray, $path);
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testGetValueThrowsExceptionIfNotArrayAccess()
    {
        $this->propertyAccessor->getValue(new \stdClass(), '[index]');
    }

    public function testGetValueReadsMagicGet()
    {
        $this->assertSame('Bernhard', $this->propertyAccessor->getValue(new TestClassMagicGet('Bernhard'), 'magicProperty'));
    }

    // https://github.com/symfony/symfony/pull/4450
    public function testGetValueReadsMagicGetThatReturnsConstant()
    {
        $this->assertSame('constant value', $this->propertyAccessor->getValue(new TestClassMagicGet('Bernhard'), 'constantMagicProperty'));
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testGetValueDoesNotReadMagicCallByDefault()
    {
        $this->propertyAccessor->getValue(new TestClassMagicCall('Bernhard'), 'magicCallProperty');
    }

    public function testGetValueReadsMagicCallIfEnabled()
    {
        $this->propertyAccessor = new PropertyAccessor(true);

        $this->assertSame('Bernhard', $this->propertyAccessor->getValue(new TestClassMagicCall('Bernhard'), 'magicCallProperty'));
    }

    // https://github.com/symfony/symfony/pull/4450
    public function testGetValueReadsMagicCallThatReturnsConstant()
    {
        $this->propertyAccessor = new PropertyAccessor(true);

        $this->assertSame('constant value', $this->propertyAccessor->getValue(new TestClassMagicCall('Bernhard'), 'constantMagicCallProperty'));
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException
     */
    public function testGetValueThrowsExceptionIfNotObjectOrArray()
    {
        $this->propertyAccessor->getValue('baz', 'foobar');
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException
     */
    public function testGetValueThrowsExceptionIfNull()
    {
        $this->propertyAccessor->getValue(null, 'foobar');
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException
     */
    public function testGetValueThrowsExceptionIfEmpty()
    {
        $this->propertyAccessor->getValue('', 'foobar');
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testSetValue($objectOrArray, $path)
    {
        $this->propertyAccessor->setValue($objectOrArray, $path, 'Updated');

        $this->assertSame('Updated', $this->propertyAccessor->getValue($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingProperty
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testSetValueThrowsExceptionIfPropertyNotFound($objectOrArray, $path)
    {
        $this->propertyAccessor->setValue($objectOrArray, $path, 'Updated');
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testSetValueThrowsNoExceptionIfIndexNotFound($objectOrArray, $path)
    {
        $this->propertyAccessor->setValue($objectOrArray, $path, 'Updated');

        $this->assertSame('Updated', $this->propertyAccessor->getValue($objectOrArray, $path));
    }

    /**
     * @dataProvider getPathsWithMissingIndex
     */
    public function testSetValueThrowsNoExceptionIfIndexNotFoundAndIndexExceptionsEnabled($objectOrArray, $path)
    {
        $this->propertyAccessor = new PropertyAccessor(false, true);
        $this->propertyAccessor->setValue($objectOrArray, $path, 'Updated');

        $this->assertSame('Updated', $this->propertyAccessor->getValue($objectOrArray, $path));
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testSetValueThrowsExceptionIfNotArrayAccess()
    {
        $this->propertyAccessor->setValue(new \stdClass(), '[index]', 'Updated');
    }

    public function testSetValueUpdatesMagicSet()
    {
        $author = new TestClassMagicGet('Bernhard');

        $this->propertyAccessor->setValue($author, 'magicProperty', 'Updated');

        $this->assertEquals('Updated', $author->__get('magicProperty'));
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testSetValueDoesNotUpdateMagicCallByDefault()
    {
        $author = new TestClassMagicCall('Bernhard');

        $this->propertyAccessor->setValue($author, 'magicCallProperty', 'Updated');
    }

    public function testSetValueUpdatesMagicCallIfEnabled()
    {
        $this->propertyAccessor = new PropertyAccessor(true);

        $author = new TestClassMagicCall('Bernhard');

        $this->propertyAccessor->setValue($author, 'magicCallProperty', 'Updated');

        $this->assertEquals('Updated', $author->__call('getMagicCallProperty', array()));
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException
     */
    public function testSetValueThrowsExceptionIfNotObjectOrArray()
    {
        $value = 'baz';

        $this->propertyAccessor->setValue($value, 'foobar', 'bam');
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException
     */
    public function testSetValueThrowsExceptionIfNull()
    {
        $value = null;

        $this->propertyAccessor->setValue($value, 'foobar', 'bam');
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException
     */
    public function testSetValueThrowsExceptionIfEmpty()
    {
        $value = '';

        $this->propertyAccessor->setValue($value, 'foobar', 'bam');
    }
}
