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
        $array = array('child' => array('index' => array()));

        $this->assertNull($this->propertyAccessor->getValue($array, '[child][index][firstName]'));
    }

    public function testGetValueReadsProperty()
    {
        $object = new Author();
        $object->firstName = 'Bernhard';

        $this->assertEquals('Bernhard', $this->propertyAccessor->getValue($object, 'firstName'));
    }

    public function testGetValueIgnoresSingular()
    {
        $this->markTestSkipped('This feature is temporarily disabled as of 2.1');

        $object = (object) array('children' => 'Many');

        $this->assertEquals('Many', $this->propertyAccessor->getValue($object, 'children|child'));
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
     * @expectedException \Symfony\Component\PropertyAccess\Exception\PropertyAccessDeniedException
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
     * @expectedException \Symfony\Component\PropertyAccess\Exception\PropertyAccessDeniedException
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
     * @expectedException \Symfony\Component\PropertyAccess\Exception\PropertyAccessDeniedException
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
     * @expectedException \Symfony\Component\PropertyAccess\Exception\PropertyAccessDeniedException
     */
    public function testSetValueThrowsExceptionIfGetterIsNotPublic()
    {
        $this->propertyAccessor->setValue(new Author(), 'privateSetter', 'foobar');
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
