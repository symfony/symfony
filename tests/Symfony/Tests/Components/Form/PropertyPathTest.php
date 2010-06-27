<?php

namespace Symfony\Tests\Components\Form;

use Symfony\Components\Form\PropertyPath;

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

    public function testToString()
    {
        $path = new PropertyPath('reference.traversable[index].property');

        $this->assertEquals('reference.traversable[index].property', $path->__toString());
    }

    public function testInvalidPropertyPath_noDotBeforeProperty()
    {
        $this->setExpectedException('Symfony\Components\Form\Exception\InvalidPropertyPathException');

        new PropertyPath('[index]property');
    }

    public function testInvalidPropertyPath_dotAtTheBeginning()
    {
        $this->setExpectedException('Symfony\Components\Form\Exception\InvalidPropertyPathException');

        new PropertyPath('.property');
    }

    public function testInvalidPropertyPath_unexpectedCharacters()
    {
        $this->setExpectedException('Symfony\Components\Form\Exception\InvalidPropertyPathException');

        new PropertyPath('property.$field');
    }

    public function testInvalidPropertyPath_empty()
    {
        $this->setExpectedException('Symfony\Components\Form\Exception\InvalidPropertyPathException');

        new PropertyPath('');
    }

    public function testNextThrowsExceptionIfNoNextElement()
    {
        $path = new PropertyPath('property');

        $this->setExpectedException('OutOfBoundsException');

        $path->next();
    }
}