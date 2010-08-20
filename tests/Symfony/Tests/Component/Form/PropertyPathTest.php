<?php

namespace Symfony\Tests\Component\Form;

use Symfony\Component\Form\PropertyPath;

class PropertyPathTest extends \PHPUnit_Framework_TestCase
{
    public function testValidPropertyPath()
    {
        $path = new PropertyPath('reference.traversable[index].property');

        $this->assertEquals('reference', $path->getCurrent());
        $this->assertTrue($path->hasNext());
        $this->assertTrue($path->isProperty());
        $this->assertFalse($path->isIndex());

        $path->next();

        $this->assertEquals('traversable', $path->getCurrent());
        $this->assertTrue($path->hasNext());
        $this->assertTrue($path->isProperty());
        $this->assertFalse($path->isIndex());

        $path->next();

        $this->assertEquals('index', $path->getCurrent());
        $this->assertTrue($path->hasNext());
        $this->assertFalse($path->isProperty());
        $this->assertTrue($path->isIndex());

        $path->next();

        $this->assertEquals('property', $path->getCurrent());
        $this->assertFalse($path->hasNext());
        $this->assertTrue($path->isProperty());
        $this->assertFalse($path->isIndex());
    }

    public function testValidPropertyPath_zero()
    {
        $path = new PropertyPath('0');

        $this->assertEquals('0', $path->getCurrent());
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

        new PropertyPath('property.$field');
    }

    public function testInvalidPropertyPath_empty()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\InvalidPropertyPathException');

        new PropertyPath('');
    }

    public function testInvalidPropertyPath_null()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\InvalidPropertyPathException');

        new PropertyPath(null);
    }

    public function testNextThrowsExceptionIfNoNextElement()
    {
        $path = new PropertyPath('property');

        $this->setExpectedException('OutOfBoundsException');

        $path->next();
    }
}