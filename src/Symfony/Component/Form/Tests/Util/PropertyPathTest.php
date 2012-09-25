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

        $path = new PropertyPath('[firstName]');

        $this->assertEquals('Bernhard', $path->getValue($array));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\InvalidPropertyException
     */
    public function testGetValueThrowsExceptionIfIndexNotationExpected()
    {
        $array = array('firstName' => 'Bernhard');

        $path = new PropertyPath('firstName');

        $path->getValue($array);
    }

    public function testGetValueReadsZeroIndex()
    {
        $array = array('Bernhard');

        $path = new PropertyPath('[0]');

        $this->assertEquals('Bernhard', $path->getValue($array));
    }

    public function testGetValueReadsIndexWithSpecialChars()
    {
        $array = array('%!@$§.' => 'Bernhard');

        $path = new PropertyPath('[%!@$§.]');

        $this->assertEquals('Bernhard', $path->getValue($array));
    }

    public function testGetValueReadsNestedIndexWithSpecialChars()
    {
        $array = array('root' => array('%!@$§.' => 'Bernhard'));

        $path = new PropertyPath('[root][%!@$§.]');

        $this->assertEquals('Bernhard', $path->getValue($array));
    }

    public function testGetValueReadsArrayWithCustomPropertyPath()
    {
        $array = array('child' => array('index' => array('firstName' => 'Bernhard')));

        $path = new PropertyPath('[child][index][firstName]');

        $this->assertEquals('Bernhard', $path->getValue($array));
    }

    public function testGetValueReadsArrayWithMissingIndexForCustomPropertyPath()
    {
        $array = array('child' => array('index' => array()));

        $path = new PropertyPath('[child][index][firstName]');

        $this->assertNull($path->getValue($array));
    }

    public function testGetValueReadsProperty()
    {
        $object = new Author();
        $object->firstName = 'Bernhard';

        $path = new PropertyPath('firstName');

        $this->assertEquals('Bernhard', $path->getValue($object));
    }

    public function testGetValueIgnoresSingular()
    {
        $this->markTestSkipped('This feature is temporarily disabled as of 2.1');

        $object = (object) array('children' => 'Many');

        $path = new PropertyPath('children|child');

        $this->assertEquals('Many', $path->getValue($object));
    }

    public function testGetValueReadsPropertyWithSpecialCharsExceptDot()
    {
        $array = (object) array('%!@$§' => 'Bernhard');

        $path = new PropertyPath('%!@$§');

        $this->assertEquals('Bernhard', $path->getValue($array));
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

    /**
     * @expectedException Symfony\Component\Form\Exception\PropertyAccessDeniedException
     */
    public function testGetValueThrowsExceptionIfPropertyIsNotPublic()
    {
        $path = new PropertyPath('privateProperty');

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

    /**
     * @expectedException Symfony\Component\Form\Exception\PropertyAccessDeniedException
     */
    public function testGetValueThrowsExceptionIfGetterIsNotPublic()
    {
        $path = new PropertyPath('privateGetter');

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

    /*
     * https://github.com/symfony/symfony/pull/4450
     */
    public function testGetValueReadsMagicGetThatReturnsConstant()
    {
        $path = new PropertyPath('magicProperty');

        $object = new Magician();

        $this->assertNull($path->getValue($object));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\PropertyAccessDeniedException
     */
    public function testGetValueThrowsExceptionIfIsserIsNotPublic()
    {
        $path = new PropertyPath('privateIsser');

        $path->getValue(new Author());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\InvalidPropertyException
     */
    public function testGetValueThrowsExceptionIfPropertyDoesNotExist()
    {
        $path = new PropertyPath('foobar');

        $path->getValue(new Author());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testGetValueThrowsExceptionIfNotObjectOrArray()
    {
        $path = new PropertyPath('foobar');

        $path->getValue('baz');
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testGetValueThrowsExceptionIfNull()
    {
        $path = new PropertyPath('foobar');

        $path->getValue(null);
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testGetValueThrowsExceptionIfEmpty()
    {
        $path = new PropertyPath('foobar');

        $path->getValue('');
    }

    public function testSetValueUpdatesArrays()
    {
        $array = array();

        $path = new PropertyPath('[firstName]');
        $path->setValue($array, 'Bernhard');

        $this->assertEquals(array('firstName' => 'Bernhard'), $array);
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\InvalidPropertyException
     */
    public function testSetValueThrowsExceptionIfIndexNotationExpected()
    {
        $array = array();

        $path = new PropertyPath('firstName');
        $path->setValue($array, 'Bernhard');
    }

    public function testSetValueUpdatesArraysWithCustomPropertyPath()
    {
        $array = array();

        $path = new PropertyPath('[child][index][firstName]');
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

    public function testSetValueUpdateMagicSet()
    {
        $object = new Magician();

        $path = new PropertyPath('magicProperty');
        $path->setValue($object, 'foobar');

        $this->assertEquals('foobar', $object->__get('magicProperty'));
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

    /**
     * @expectedException Symfony\Component\Form\Exception\PropertyAccessDeniedException
     */
    public function testSetValueThrowsExceptionIfGetterIsNotPublic()
    {
        $path = new PropertyPath('privateSetter');

        $path->setValue(new Author(), 'foobar');
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testSetValueThrowsExceptionIfNotObjectOrArray()
    {
        $path = new PropertyPath('foobar');
        $value = 'baz';

        $path->setValue($value, 'bam');
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testSetValueThrowsExceptionIfNull()
    {
        $path = new PropertyPath('foobar');
        $value = null;

        $path->setValue($value, 'bam');
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testSetValueThrowsExceptionIfEmpty()
    {
        $path = new PropertyPath('foobar');
        $value = '';

        $path->setValue($value, 'bam');
    }

    public function testToString()
    {
        $path = new PropertyPath('reference.traversable[index].property');

        $this->assertEquals('reference.traversable[index].property', $path->__toString());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\InvalidPropertyPathException
     */
    public function testInvalidPropertyPath_noDotBeforeProperty()
    {
        new PropertyPath('[index]property');
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\InvalidPropertyPathException
     */
    public function testInvalidPropertyPath_dotAtTheBeginning()
    {
        new PropertyPath('.property');
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\InvalidPropertyPathException
     */
    public function testInvalidPropertyPath_unexpectedCharacters()
    {
        new PropertyPath('property.$form');
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\InvalidPropertyPathException
     */
    public function testInvalidPropertyPath_empty()
    {
        new PropertyPath('');
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testInvalidPropertyPath_null()
    {
        new PropertyPath(null);
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testInvalidPropertyPath_false()
    {
        new PropertyPath(false);
    }

    public function testValidPropertyPath_zero()
    {
        new PropertyPath('0');
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

    public function testCopyConstructor()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');
        $copy = new PropertyPath($propertyPath);

        $this->assertEquals($propertyPath, $copy);
    }

    public function testGetElement()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $this->assertEquals('child', $propertyPath->getElement(2));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetElementDoesNotAcceptInvalidIndices()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->getElement(3);
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetElementDoesNotAcceptNegativeIndices()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->getElement(-1);
    }

    public function testIsProperty()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $this->assertTrue($propertyPath->isProperty(1));
        $this->assertFalse($propertyPath->isProperty(2));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testIsPropertyDoesNotAcceptInvalidIndices()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->isProperty(3);
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testIsPropertyDoesNotAcceptNegativeIndices()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->isProperty(-1);
    }

    public function testIsIndex()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $this->assertFalse($propertyPath->isIndex(1));
        $this->assertTrue($propertyPath->isIndex(2));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testIsIndexDoesNotAcceptInvalidIndices()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->isIndex(3);
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testIsIndexDoesNotAcceptNegativeIndices()
    {
        $propertyPath = new PropertyPath('grandpa.parent[child]');

        $propertyPath->isIndex(-1);
    }
}
