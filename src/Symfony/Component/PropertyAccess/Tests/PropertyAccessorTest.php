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
use Symfony\Component\PropertyAccess\Tests\Fixtures\Author;
use Symfony\Component\PropertyAccess\Tests\Fixtures\Magician;
use Symfony\Component\PropertyAccess\Tests\Fixtures\MagicianCall;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TestClass;
use Symfony\Component\PropertyAccess\Tests\Fixtures\Ticket5775Object;
use Symfony\Component\PropertyAccess\Tests\Fixtures\TypeHinted;

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

    public function getPathsWithUnexpectedType()
    {
        return array(
            array('', 'foobar'),
            array('foo', 'foobar'),
            array(null, 'foobar'),
            array(123, 'foobar'),
            array((object) array('prop' => null), 'prop.foobar'),
            array((object) array('prop' => (object) array('subProp' => null)), 'prop.subProp.foobar'),
            array(array('index' => null), '[index][foobar]'),
            array(array('index' => array('subIndex' => null)), '[index][subIndex][foobar]'),
        );
    }

    public function testGetValueReadsArray()
    {
        $array = array('firstName' => 'Bernhard');

        $this->assertEquals('Bernhard', $this->propertyAccessor->getValue($array, '[firstName]'));
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testGetValueThrowsExceptionIfIndexNotationExpected()
    {
        $array = array('firstName' => 'Bernhard');

        $this->propertyAccessor->getValue($array, 'firstName');
    }

    public function testGetValueReadsZeroIndex()
    {
        $array = array('Bernhard');

        $this->assertEquals('Bernhard', $this->propertyAccessor->getValue($array, '[0]'));
    }

    public function testGetValueReadsIndexWithSpecialChars()
    {
        $array = array('%!@$§.' => 'Bernhard');

        $this->assertEquals('Bernhard', $this->propertyAccessor->getValue($array, '[%!@$§.]'));
    }

    public function testGetValueReadsNestedIndexWithSpecialChars()
    {
        $array = array('root' => array('%!@$§.' => 'Bernhard'));

        $this->assertEquals('Bernhard', $this->propertyAccessor->getValue($array, '[root][%!@$§.]'));
    }

    public function testGetValueReadsArrayWithCustomPropertyPath()
    {
        $array = array('child' => array('index' => array('firstName' => 'Bernhard')));

        $this->assertEquals('Bernhard', $this->propertyAccessor->getValue($array, '[child][index][firstName]'));
    }

    public function testGetValueReadsArrayWithMissingIndexForCustomPropertyPath()
    {
        $object = new \ArrayObject();
        $array = array('child' => array('index' => $object));

        $this->assertNull($this->propertyAccessor->getValue($array, '[child][index][foo][bar]'));
        $this->assertSame(array(), $object->getArrayCopy());
    }

    public function testGetValueReadsProperty()
    {
        $object = new Author();
        $object->firstName = 'Bernhard';

        $this->assertEquals('Bernhard', $this->propertyAccessor->getValue($object, 'firstName'));
    }

    public function testGetValueNotModifyObject()
    {
        $object = new Author();
        $object->firstName = array('Bernhard');

        $this->assertNull($this->propertyAccessor->getValue($object, 'firstName[1]'));
        $this->assertSame(array('Bernhard'), $object->firstName);
    }

    public function testGetValueReadsPropertyWithSpecialCharsExceptDot()
    {
        $array = (object) array('%!@$§' => 'Bernhard');

        $this->assertEquals('Bernhard', $this->propertyAccessor->getValue($array, '%!@$§'));
    }

    public function testGetValueReadsPropertyWithCustomPropertyPath()
    {
        $object = new Author();
        $object->child = array();
        $object->child['index'] = new Author();
        $object->child['index']->firstName = 'Bernhard';

        $this->assertEquals('Bernhard', $this->propertyAccessor->getValue($object, 'child[index].firstName'));
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testGetValueThrowsExceptionIfPropertyIsNotPublic()
    {
        $this->propertyAccessor->getValue(new Author(), 'privateProperty');
    }

    public function testGetValueReadsGetters()
    {
        $object = new Author();
        $object->setLastName('Schussek');

        $this->assertEquals('Schussek', $this->propertyAccessor->getValue($object, 'lastName'));
    }

    public function testGetValueCamelizesGetterNames()
    {
        $object = new Author();
        $object->setLastName('Schussek');

        $this->assertEquals('Schussek', $this->propertyAccessor->getValue($object, 'last_name'));
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testGetValueThrowsExceptionIfGetterIsNotPublic()
    {
        $this->propertyAccessor->getValue(new Author(), 'privateGetter');
    }

    public function testGetValueReadsIssers()
    {
        $object = new Author();
        $object->setAustralian(false);

        $this->assertFalse($this->propertyAccessor->getValue($object, 'australian'));
    }

    public function testGetValueReadHassers()
    {
        $object = new Author();
        $object->setReadPermissions(true);

        $this->assertTrue($this->propertyAccessor->getValue($object, 'read_permissions'));
    }

    public function testGetValueReadsMagicGet()
    {
        $object = new Magician();
        $object->__set('magicProperty', 'foobar');

        $this->assertSame('foobar', $this->propertyAccessor->getValue($object, 'magicProperty'));
    }

    /*
     * https://github.com/symfony/symfony/pull/4450
     */
    public function testGetValueReadsMagicGetThatReturnsConstant()
    {
        $object = new Magician();

        $this->assertNull($this->propertyAccessor->getValue($object, 'magicProperty'));
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testGetValueThrowsExceptionIfIsserIsNotPublic()
    {
        $this->propertyAccessor->getValue(new Author(), 'privateIsser');
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testGetValueThrowsExceptionIfPropertyDoesNotExist()
    {
        $this->propertyAccessor->getValue(new Author(), 'foobar');
    }

    /**
     * @dataProvider getPathsWithUnexpectedType
     * @expectedException \Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException
     * @expectedExceptionMessage Expected argument of type "object or array"
     */
    public function testGetValueThrowsExceptionIfNotObjectOrArray($objectOrArray, $path)
    {
        $this->propertyAccessor->getValue($objectOrArray, $path);
    }

    public function testGetValueWhenArrayValueIsNull()
    {
        $this->propertyAccessor = new PropertyAccessor(false, true);
        $this->assertNull($this->propertyAccessor->getValue(array('index' => array('nullable' => null)), '[index][nullable]'));
    }

    public function testSetValueUpdatesArrays()
    {
        $array = array();

        $this->propertyAccessor->setValue($array, '[firstName]', 'Bernhard');

        $this->assertEquals(array('firstName' => 'Bernhard'), $array);
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testSetValueThrowsExceptionIfIndexNotationExpected()
    {
        $array = array();

        $this->propertyAccessor->setValue($array, 'firstName', 'Bernhard');
    }

    public function testSetValueUpdatesArraysWithCustomPropertyPath()
    {
        $array = array();

        $this->propertyAccessor->setValue($array, '[child][index][firstName]', 'Bernhard');

        $this->assertEquals(array('child' => array('index' => array('firstName' => 'Bernhard'))), $array);
    }

    public function testSetValueUpdatesProperties()
    {
        $object = new Author();

        $this->propertyAccessor->setValue($object, 'firstName', 'Bernhard');

        $this->assertEquals('Bernhard', $object->firstName);
    }

    public function testSetValueUpdatesPropertiesWithCustomPropertyPath()
    {
        $object = new Author();
        $object->child = array();
        $object->child['index'] = new Author();

        $this->propertyAccessor->setValue($object, 'child[index].firstName', 'Bernhard');

        $this->assertEquals('Bernhard', $object->child['index']->firstName);
    }

    public function testSetValueUpdateMagicSet()
    {
        $object = new Magician();

        $this->propertyAccessor->setValue($object, 'magicProperty', 'foobar');

        $this->assertEquals('foobar', $object->__get('magicProperty'));
    }

    public function testSetValueUpdatesSetters()
    {
        $object = new Author();

        $this->propertyAccessor->setValue($object, 'lastName', 'Schussek');

        $this->assertEquals('Schussek', $object->getLastName());
    }

    public function testSetValueCamelizesSetterNames()
    {
        $object = new Author();

        $this->propertyAccessor->setValue($object, 'last_name', 'Schussek');

        $this->assertEquals('Schussek', $object->getLastName());
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testSetValueThrowsExceptionIfGetterIsNotPublic()
    {
        $object = new Author();

        $this->propertyAccessor->setValue($object, 'privateSetter', 'foobar');
    }

    /**
     * @dataProvider getPathsWithUnexpectedType
     * @expectedException \Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException
     * @expectedExceptionMessage Expected argument of type "object or array"
     */
    public function testSetValueThrowsExceptionIfNotObjectOrArray($objectOrArray, $path)
    {
        $this->propertyAccessor->setValue($objectOrArray, $path, 'value');
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testSetValueFailsIfMagicCallDisabled()
    {
        $value = new MagicianCall();

        $this->propertyAccessor->setValue($value, 'foobar', 'bam');
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testGetValueFailsIfMagicCallDisabled()
    {
        $value = new MagicianCall();

        $this->propertyAccessor->getValue($value, 'foobar');
    }

    public function testGetValueReadsMagicCall()
    {
        $propertyAccessor = new PropertyAccessor(true);
        $object = new MagicianCall();
        $object->setMagicProperty('foobar');

        $this->assertSame('foobar', $propertyAccessor->getValue($object, 'magicProperty'));
    }

    public function testGetValueReadsMagicCallThatReturnsConstant()
    {
        $propertyAccessor = new PropertyAccessor(true);
        $object = new MagicianCall();

        $this->assertNull($propertyAccessor->getValue($object, 'MagicProperty'));
    }

    public function testSetValueUpdatesMagicCall()
    {
        $propertyAccessor = new PropertyAccessor(true);
        $object = new MagicianCall();

        $propertyAccessor->setValue($object, 'magicProperty', 'foobar');

        $this->assertEquals('foobar', $object->getMagicProperty());
    }

    public function testTicket5755()
    {
        $object = new Ticket5775Object();

        $this->propertyAccessor->setValue($object, 'property', 'foobar');

        $this->assertEquals('foobar', $object->getProperty());
    }

    /**
     * @dataProvider getValidPropertyPaths
     */
    public function testSetValue($objectOrArray, $path)
    {
        $this->propertyAccessor->setValue($objectOrArray, $path, 'Updated');

        $this->assertSame('Updated', $this->propertyAccessor->getValue($objectOrArray, $path));
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

            // Missing indices
            array(array('index' => array()), '[index][firstName]', null),
            array(array('root' => array('index' => array())), '[root][index][firstName]', null),
        );
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\InvalidArgumentException
     * @expectedExceptionMessage Expected argument of type "DateTime", "string" given
     */
    public function testThrowTypeError()
    {
        $object = new TypeHinted();

        $this->propertyAccessor->setValue($object, 'date', 'This is a string, \DateTime expected.');
    }

    public function testSetTypeHint()
    {
        $date = new \DateTime();
        $object = new TypeHinted();

        $this->propertyAccessor->setValue($object, 'date', $date);
        $this->assertSame($date, $object->getDate());
    }

    public function testArrayNotBeeingOverwritten()
    {
        $value = array('value1' => 'foo', 'value2' => 'bar');
        $object = new TestClass($value);

        $this->propertyAccessor->setValue($object, 'publicAccessor[value2]', 'baz');
        $this->assertSame('baz', $this->propertyAccessor->getValue($object, 'publicAccessor[value2]'));
        $this->assertSame(array('value1' => 'foo', 'value2' => 'baz'), $object->getPublicAccessor());
    }
}
