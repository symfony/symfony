<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Util;

use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Form\Tests\Fixtures\Author;
use Symfony\Component\Form\Tests\Fixtures\Magician;

class PropertyPathTest extends \PHPUnit_Framework_TestCase
{
    public function testGetValueReadsArray()
    {
        $array = array('firstName' => 'Bernhard');

        $path = new PropertyPath('firstName');

        $this->assertEquals('Bernhard', $path->getValue($array));
    }

    public function testGetValueIgnoresSingular()
    {
        $array = array('children' => 'Many');

        $path = new PropertyPath('children|child');

        $this->assertEquals('Many', $path->getValue($array));
    }

    public function testGetValueReadsZeroIndex()
    {
        $array = array('Bernhard');

        $path = new PropertyPath('0');

        $this->assertEquals('Bernhard', $path->getValue($array));
    }

    public function testGetValueReadsIndexWithSpecialChars()
    {
        $array = array('%!@$§.' => 'Bernhard');

        $path = new PropertyPath('[%!@$§.]');

        $this->assertEquals('Bernhard', $path->getValue($array));
    }

    public function testGetValueReadsElementWithSpecialCharsExceptDot()
    {
        $array = array('%!@$§' => 'Bernhard');

        $path = new PropertyPath('%!@$§');

        $this->assertEquals('Bernhard', $path->getValue($array));
    }

    public function testGetValueReadsNestedIndexWithSpecialChars()
    {
        $array = array('root' => array('%!@$§.' => 'Bernhard'));

        $path = new PropertyPath('root[%!@$§.]');

        $this->assertEquals('Bernhard', $path->getValue($array));
    }

    public function testGetValueReadsArrayWithCustomPropertyPath()
    {
        $array = array('child' => array('index' => array('firstName' => 'Bernhard')));

        $path = new PropertyPath('child[index].firstName');

        $this->assertEquals('Bernhard', $path->getValue($array));
    }

    public function testGetValueReadsArrayWithMissingIndexForCustomPropertyPath()
    {
        $array = array('child' => array('index' => array()));

        $path = new PropertyPath('child[index].firstName');

        $this->assertNull($path->getValue($array));
    }

    public function testGetValueReadsProperty()
    {
        $object = new Author();
        $object->firstName = 'Bernhard';

        $path = new PropertyPath('firstName');

        $this->assertEquals('Bernhard', $path->getValue($object));
    }

    public function testGetValueReadsPropertyWithCustomPropertyPath()
    {
        $object = new Author();
        $object->child = array();
        $object->child['index'] = new Author();
        $object->child['index']->firstName = 'Bernhard';

        $path = new PropertyPath('child[index].firstName');

        $this->assertEquals('Bernhard', $path->getValue($object));
    }

    public function testGetValueReadsArrayAccess()
    {
        $object = new \ArrayObject();
        $object['firstName'] = 'Bernhard';

        $path = new PropertyPath('[firstName]');

        $this->assertEquals('Bernhard', $path->getValue($object));
    }

    public function testGetValueThrowsExceptionIfArrayAccessExpected()
    {
        $path = new PropertyPath('[firstName]');

        $this->setExpectedException('Symfony\Component\Form\Exception\InvalidPropertyException');

        $path->getValue(new Author());
    }

    public function testGetValueThrowsExceptionIfPropertyIsNotPublic()
    {
        $path = new PropertyPath('privateProperty');

        $this->setExpectedException('Symfony\Component\Form\Exception\PropertyAccessDeniedException');

        $path->getValue(new Author());
    }

    public function testGetValueReadsGetters()
    {
        $path = new PropertyPath('lastName');

        $object = new Author();
        $object->setLastName('Schussek');

        $this->assertEquals('Schussek', $path->getValue($object));
    }

    public function testGetValueCamelizesGetterNames()
    {
        $path = new PropertyPath('last_name');

        $object = new Author();
        $object->setLastName('Schussek');

        $this->assertEquals('Schussek', $path->getValue($object));
    }

    public function testGetValueThrowsExceptionIfGetterIsNotPublic()
    {
        $path = new PropertyPath('privateGetter');

        $this->setExpectedException('Symfony\Component\Form\Exception\PropertyAccessDeniedException');

        $path->getValue(new Author());
    }

    public function testGetValueReadsIssers()
    {
        $path = new PropertyPath('australian');

        $object = new Author();
        $object->setAustralian(false);

        $this->assertFalse($path->getValue($object));
    }

    public function testGetValueReadHassers()
    {
        $path = new PropertyPath('read_permissions');

        $object = new Author();
        $object->setReadPermissions(true);

        $this->assertTrue($path->getValue($object));
    }

    public function testGetValueReadsMagicGet()
    {
        $path = new PropertyPath('magicProperty');

        $object = new Magician();
        $object->__set('magicProperty', 'foobar');

        $this->assertSame('foobar', $path->getValue($object));
    }

    public function testGetValueThrowsExceptionIfIsserIsNotPublic()
    {
        $path = new PropertyPath('privateIsser');

        $this->setExpectedException('Symfony\Component\Form\Exception\PropertyAccessDeniedException');

        $path->getValue(new Author());
    }

    public function testGetValueThrowsExceptionIfPropertyDoesNotExist()
    {
        $path = new PropertyPath('foobar');

        $this->setExpectedException('Symfony\Component\Form\Exception\InvalidPropertyException');

        $path->getValue(new Author());
    }

    public function testGetValueThrowsExceptionIfNotObjectOrArray()
    {
        $path = new PropertyPath('foobar');

        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');

        $path->getValue('baz');
    }

    public function testGetValueThrowsExceptionIfNull()
    {
        $path = new PropertyPath('foobar');

        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');

        $path->getValue(null);
    }

    public function testGetValueThrowsExceptionIfEmpty()
    {
        $path = new PropertyPath('foobar');

        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');

        $path->getValue('');
    }

    public function testSetValueUpdatesArrays()
    {
        $array = array();

        $path = new PropertyPath('firstName');
        $path->setValue($array, 'Bernhard');

        $this->assertEquals(array('firstName' => 'Bernhard'), $array);
    }

    public function testSetValueUpdatesArraysWithCustomPropertyPath()
    {
        $array = array();

        $path = new PropertyPath('child[index].firstName');
        $path->setValue($array, 'Bernhard');

        $this->assertEquals(array('child' => array('index' => array('firstName' => 'Bernhard'))), $array);
    }

    public function testSetValueUpdatesProperties()
    {
        $object = new Author();

        $path = new PropertyPath('firstName');
        $path->setValue($object, 'Bernhard');

        $this->assertEquals('Bernhard', $object->firstName);
    }

    public function testSetValueUpdatesPropertiesWithCustomPropertyPath()
    {
        $object = new Author();
        $object->child = array();
        $object->child['index'] = new Author();

        $path = new PropertyPath('child[index].firstName');
        $path->setValue($object, 'Bernhard');

        $this->assertEquals('Bernhard', $object->child['index']->firstName);
    }

    public function testSetValueUpdatesArrayAccess()
    {
        $object = new \ArrayObject();

        $path = new PropertyPath('[firstName]');
        $path->setValue($object, 'Bernhard');

        $this->assertEquals('Bernhard', $object['firstName']);
    }

    public function testSetValueUpdateMagicSet()
    {
        $object = new Magician();

        $path = new PropertyPath('magicProperty');
        $path->setValue($object, 'foobar');

        $this->assertEquals('foobar', $object->__get('magicProperty'));
    }

    public function testSetValueThrowsExceptionIfArrayAccessExpected()
    {
        $path = new PropertyPath('[firstName]');

        $this->setExpectedException('Symfony\Component\Form\Exception\InvalidPropertyException');

        $path->setValue(new Author(), 'Bernhard');
    }

    public function testSetValueUpdatesSetters()
    {
        $object = new Author();

        $path = new PropertyPath('lastName');
        $path->setValue($object, 'Schussek');

        $this->assertEquals('Schussek', $object->getLastName());
    }

    public function testSetValueCamelizesSetterNames()
    {
        $object = new Author();

        $path = new PropertyPath('last_name');
        $path->setValue($object, 'Schussek');

        $this->assertEquals('Schussek', $object->getLastName());
    }

    public function testSetValueThrowsExceptionIfGetterIsNotPublic()
    {
        $path = new PropertyPath('privateSetter');

        $this->setExpectedException('Symfony\Component\Form\Exception\PropertyAccessDeniedException');

        $path->setValue(new Author(), 'foobar');
    }

    public function testSetValueThrowsExceptionIfNotObjectOrArray()
    {
        $path = new PropertyPath('foobar');
        $value = 'baz';

        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');

        $path->setValue($value, 'bam');
    }

    public function testSetValueThrowsExceptionIfNull()
    {
        $path = new PropertyPath('foobar');
        $value = null;

        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');

        $path->setValue($value, 'bam');
    }

    public function testSetValueThrowsExceptionIfEmpty()
    {
        $path = new PropertyPath('foobar');
        $value = '';

        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');

        $path->setValue($value, 'bam');
    }

    public function testToString()
    {
        $path = new PropertyPath('reference.traversable[index].property');

        $this->assertEquals('reference.traversable[index].property', $path->__toString());
    }

    public function testInvalidPropertyPath_noDotBeforeProperty()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\InvalidPropertyPathException');

        new PropertyPath('[index]property');
    }

    public function testInvalidPropertyPath_dotAtTheBeginning()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\InvalidPropertyPathException');

        new PropertyPath('.property');
    }

    public function testInvalidPropertyPath_unexpectedCharacters()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\InvalidPropertyPathException');

        new PropertyPath('property.$form');
    }

    public function testInvalidPropertyPath_null()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\InvalidPropertyPathException');

        new PropertyPath(null);
    }

    public function testGetParent_dot()
    {
        $propertyPath = new PropertyPath('grandpa.parent.child');

        $this->assertEquals(new PropertyPath('grandpa.parent'), $propertyPath->getParent());
    }

    public function testGetParent_index()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $this->assertEquals(new PropertyPath('grandpa.parent'), $propertyPath->getParent());
    }

    public function testGetParent_noParent()
    {
        $propertyPath = new PropertyPath('path');

        $this->assertNull($propertyPath->getParent());
    }
}
