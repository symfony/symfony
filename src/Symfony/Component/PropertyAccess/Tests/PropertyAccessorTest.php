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
use Symfony\Component\PropertyAccess\PropertyAccessorBuilder;

class PropertyAccessorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PropertyAccessorBuilder
     */
    private $propertyAccessorBuilder;

    protected function setUp()
    {
        $this->propertyAccessorBuilder = new PropertyAccessorBuilder();
    }

    /**
     * Get PropertyAccessor configured
     *
     * @param string $withMagicCall
     * @param string $throwExceptionOnInvalidIndex
     * @return PropertyAccessorInterface
     */
    protected function getPropertyAccessor($withMagicCall = false, $throwExceptionOnInvalidIndex = false)
    {
        if ($withMagicCall) {
            $this->propertyAccessorBuilder->enableMagicCall();
        } else {
            $this->propertyAccessorBuilder->disableMagicCall();
        }

        if ($throwExceptionOnInvalidIndex) {
            $this->propertyAccessorBuilder->enableExceptionOnInvalidIndex();
        } else {
            $this->propertyAccessorBuilder->disableExceptionOnInvalidIndex();
        }

        return $this->propertyAccessorBuilder->getPropertyAccessor();
    }

    public function testGetValueReadsArray()
    {
        $array = array('firstName' => 'Bernhard');

        $this->assertEquals('Bernhard', $this->getPropertyAccessor()->getValue($array, '[firstName]'));
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testGetValueThrowsExceptionIfIndexNotationExpected()
    {
        $array = array('firstName' => 'Bernhard');

        $this->getPropertyAccessor()->getValue($array, 'firstName');
    }

    public function testGetValueReadsZeroIndex()
    {
        $array = array('Bernhard');

        $this->assertEquals('Bernhard', $this->getPropertyAccessor()->getValue($array, '[0]'));
    }

    public function testGetValueReadsIndexWithSpecialChars()
    {
        $array = array('%!@$§.' => 'Bernhard');

        $this->assertEquals('Bernhard', $this->getPropertyAccessor()->getValue($array, '[%!@$§.]'));
    }

    public function testGetValueReadsNestedIndexWithSpecialChars()
    {
        $array = array('root' => array('%!@$§.' => 'Bernhard'));

        $this->assertEquals('Bernhard', $this->getPropertyAccessor()->getValue($array, '[root][%!@$§.]'));
    }

    public function testGetValueReadsArrayWithCustomPropertyPath()
    {
        $array = array('child' => array('index' => array('firstName' => 'Bernhard')));

        $this->assertEquals('Bernhard', $this->getPropertyAccessor()->getValue($array, '[child][index][firstName]'));
    }

    public function testGetValueReadsArrayWithMissingIndexForCustomPropertyPath()
    {
        $array = array('child' => array('index' => array()));

        // No BC break
        $this->assertNull($this->getPropertyAccessor()->getValue($array, '[child][index][firstName]'));

        try {
            $this->getPropertyAccessor(false, true)->getValue($array, '[child][index][firstName]');
            $this->fail('Getting value on a nonexistent path from array should throw a Symfony\Component\PropertyAccess\Exception\NoSuchIndexException exception');
        } catch (\Exception $e) {
            $this->assertInstanceof('Symfony\Component\PropertyAccess\Exception\NoSuchIndexException', $e, 'Getting value on a nonexistent path from array should throw a Symfony\Component\PropertyAccess\Exception\NoSuchIndexException exception');
        }
    }

    public function testGetValueReadsProperty()
    {
        $object = new Author();
        $object->firstName = 'Bernhard';

        $this->assertEquals('Bernhard', $this->getPropertyAccessor()->getValue($object, 'firstName'));
    }

    public function testGetValueIgnoresSingular()
    {
        $this->markTestSkipped('This feature is temporarily disabled as of 2.1');

        $object = (object) array('children' => 'Many');

        $this->assertEquals('Many', $this->getPropertyAccessor()->getValue($object, 'children|child'));
    }

    public function testGetValueReadsPropertyWithSpecialCharsExceptDot()
    {
        $array = (object) array('%!@$§' => 'Bernhard');

        $this->assertEquals('Bernhard', $this->getPropertyAccessor()->getValue($array, '%!@$§'));
    }

    public function testGetValueReadsPropertyWithSpecialCharsExceptDotNested()
    {
        $object = (object) array('nested' => (object) array('@child' => 'foo'));

        $this->assertEquals('foo', $this->getPropertyAccessor()->getValue($object, 'nested.@child'));
    }

    public function testGetValueReadsPropertyWithCustomPropertyPath()
    {
        $object = new Author();
        $object->child = array();
        $object->child['index'] = new Author();
        $object->child['index']->firstName = 'Bernhard';

        $this->assertEquals('Bernhard', $this->getPropertyAccessor()->getValue($object, 'child[index].firstName'));
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testGetValueThrowsExceptionIfPropertyIsNotPublic()
    {
        $this->getPropertyAccessor()->getValue(new Author(), 'privateProperty');
    }

    public function testGetValueReadsGetters()
    {
        $object = new Author();
        $object->setLastName('Schussek');

        $this->assertEquals('Schussek', $this->getPropertyAccessor()->getValue($object, 'lastName'));
    }

    public function testGetValueCamelizesGetterNames()
    {
        $object = new Author();
        $object->setLastName('Schussek');

        $this->assertEquals('Schussek', $this->getPropertyAccessor()->getValue($object, 'last_name'));
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testGetValueThrowsExceptionIfGetterIsNotPublic()
    {
        $this->getPropertyAccessor()->getValue(new Author(), 'privateGetter');
    }

    public function testGetValueReadsIssers()
    {
        $object = new Author();
        $object->setAustralian(false);

        $this->assertFalse($this->getPropertyAccessor()->getValue($object, 'australian'));
    }

    public function testGetValueReadHassers()
    {
        $object = new Author();
        $object->setReadPermissions(true);

        $this->assertTrue($this->getPropertyAccessor()->getValue($object, 'read_permissions'));
    }

    public function testGetValueReadsMagicGet()
    {
        $object = new Magician();
        $object->__set('magicProperty', 'foobar');

        $this->assertSame('foobar', $this->getPropertyAccessor()->getValue($object, 'magicProperty'));
    }

    /*
     * https://github.com/symfony/symfony/pull/4450
     */
    public function testGetValueReadsMagicGetThatReturnsConstant()
    {
        $object = new Magician();

        $this->assertNull($this->getPropertyAccessor()->getValue($object, 'magicProperty'));
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testGetValueThrowsExceptionIfIsserIsNotPublic()
    {
        $this->getPropertyAccessor()->getValue(new Author(), 'privateIsser');
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testGetValueThrowsExceptionIfPropertyDoesNotExist()
    {
        $this->getPropertyAccessor()->getValue(new Author(), 'foobar');
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException
     */
    public function testGetValueThrowsExceptionIfNotObjectOrArray()
    {
        $this->getPropertyAccessor()->getValue('baz', 'foobar');
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException
     */
    public function testGetValueThrowsExceptionIfNull()
    {
        $this->getPropertyAccessor()->getValue(null, 'foobar');
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException
     */
    public function testGetValueThrowsExceptionIfEmpty()
    {
        $this->getPropertyAccessor()->getValue('', 'foobar');
    }

    public function testSetValueUpdatesArrays()
    {
        $array = array();

        $this->getPropertyAccessor()->setValue($array, '[firstName]', 'Bernhard');

        $this->assertEquals(array('firstName' => 'Bernhard'), $array);
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testSetValueThrowsExceptionIfIndexNotationExpected()
    {
        $array = array();

        $this->getPropertyAccessor()->setValue($array, 'firstName', 'Bernhard');
    }

    public function testSetValueUpdatesArraysWithCustomPropertyPath()
    {
        $array = array();

        $this->getPropertyAccessor()->setValue($array, '[child][index][firstName]', 'Bernhard');

        $this->assertEquals(array('child' => array('index' => array('firstName' => 'Bernhard'))), $array);
    }

    public function testSetValueUpdatesProperties()
    {
        $object = new Author();

        $this->getPropertyAccessor()->setValue($object, 'firstName', 'Bernhard');

        $this->assertEquals('Bernhard', $object->firstName);
    }

    public function testSetValueUpdatesPropertiesWithCustomPropertyPath()
    {
        $object = new Author();
        $object->child = array();
        $object->child['index'] = new Author();

        $this->getPropertyAccessor()->setValue($object, 'child[index].firstName', 'Bernhard');

        $this->assertEquals('Bernhard', $object->child['index']->firstName);
    }

    public function testSetValueUpdateMagicSet()
    {
        $object = new Magician();

        $this->getPropertyAccessor()->setValue($object, 'magicProperty', 'foobar');

        $this->assertEquals('foobar', $object->__get('magicProperty'));
    }

    public function testSetValueUpdatesSetters()
    {
        $object = new Author();

        $this->getPropertyAccessor()->setValue($object, 'lastName', 'Schussek');

        $this->assertEquals('Schussek', $object->getLastName());
    }

    public function testSetValueCamelizesSetterNames()
    {
        $object = new Author();

        $this->getPropertyAccessor()->setValue($object, 'last_name', 'Schussek');

        $this->assertEquals('Schussek', $object->getLastName());
    }

    public function testSetValueWithSpecialCharsNested()
    {
        $object = new \stdClass();
        $person = new \stdClass();
        $person->{'@email'} = null;
        $object->person = $person;

        $this->getPropertyAccessor()->setValue($object, 'person.@email', 'bar');
        $this->assertEquals('bar', $object->person->{'@email'});
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testSetValueThrowsExceptionIfGetterIsNotPublic()
    {
        $this->getPropertyAccessor()->setValue(new Author(), 'privateSetter', 'foobar');
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException
     */
    public function testSetValueThrowsExceptionIfNotObjectOrArray()
    {
        $value = 'baz';

        $this->getPropertyAccessor()->setValue($value, 'foobar', 'bam');
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException
     */
    public function testSetValueThrowsExceptionIfNull()
    {
        $value = null;

        $this->getPropertyAccessor()->setValue($value, 'foobar', 'bam');
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException
     */
    public function testSetValueThrowsExceptionIfEmpty()
    {
        $value = '';

        $this->getPropertyAccessor()->setValue($value, 'foobar', 'bam');
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testSetValueFailsIfMagicCallDisabled()
    {
        $value = new MagicianCall();

        $this->getPropertyAccessor()->setValue($value, 'foobar', 'bam');
    }

    /**
     * @expectedException \Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException
     */
    public function testGetValueFailsIfMagicCallDisabled()
    {
        $value = new MagicianCall();

        $this->getPropertyAccessor()->getValue($value, 'foobar', 'bam');
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

}
