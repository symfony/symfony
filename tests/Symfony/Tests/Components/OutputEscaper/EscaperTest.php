<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\OutputEscaper;

require_once __DIR__.'/../../bootstrap.php';

use Symfony\Components\OutputEscaper\Escaper;
use Symfony\Components\OutputEscaper\SafeDecorator;
use Symfony\Components\OutputEscaper\IteratorDecorator;
use Symfony\Components\OutputEscaper\ArrayDecorator;
use Symfony\Components\OutputEscaper\ObjectDecorator;

class EscaperTest extends \PHPUnit_Framework_TestCase
{
  public function testEscapeDoesNotEscapeSpecialValues()
  {
    $this->assertSame(Escaper::escape('entities', null), null, '::escape() returns null if the value to escape is null');
    $this->assertSame(Escaper::escape('entities', false), false, '::escape() returns false if the value to escape is false');
    $this->assertSame(Escaper::escape('entities', true), true, '::escape() returns true if the value to escape is true');
  }

  public function testEscapeDoesNotEscapeAValueWhenEscapingMethodIsRAW()
  {
    $this->assertEquals(Escaper::escape('raw', '<strong>escaped!</strong>'), '<strong>escaped!</strong>', '::escape() takes an escaping strategy function name as its first argument');
  }

  public function testEscapeEscapesStrings()
  {
    $this->assertEquals(Escaper::escape('entities', '<strong>escaped!</strong>'), '&lt;strong&gt;escaped!&lt;/strong&gt;', '::escape() returns an escaped string if the value to escape is a string');
    $this->assertEquals(Escaper::escape('entities', '<strong>échappé</strong>'), '&lt;strong&gt;&eacute;chapp&eacute;&lt;/strong&gt;', '::escape() returns an escaped string if the value to escape is a string');
  }

  public function testEscapeEscapesArrays()
  {
    $input = array(
      'foo' => '<strong>escaped!</strong>',
      'bar' => array('foo' => '<strong>escaped!</strong>'),
    );
    $output = Escaper::escape('entities', $input);
    $this->assertTrue($output instanceof ArrayDecorator, '::escape() returns a ArrayDecorator object if the value to escape is an array');
    $this->assertEquals($output['foo'], '&lt;strong&gt;escaped!&lt;/strong&gt;', '::escape() escapes all elements of the original array');
    $this->assertEquals($output['bar']['foo'], '&lt;strong&gt;escaped!&lt;/strong&gt;', '::escape() is recursive');
    $this->assertEquals($output->getRawValue(), $input, '->getRawValue() returns the unescaped value');
  }

  public function testEscapeEscapesObjects()
  {
    $input = new OutputEscaperTestClass();
    $output = Escaper::escape('entities', $input);
    $this->assertTrue($output instanceof ObjectDecorator, '::escape() returns a ObjectDecorator object if the value to escape is an object');
    $this->assertEquals($output->getTitle(), '&lt;strong&gt;escaped!&lt;/strong&gt;', '::escape() escapes all methods of the original object');
    $this->assertEquals($output->title, '&lt;strong&gt;escaped!&lt;/strong&gt;', '::escape() escapes all properties of the original object');
    $this->assertEquals($output->getTitleTitle(), '&lt;strong&gt;escaped!&lt;/strong&gt;', '::escape() is recursive');
    $this->assertEquals($output->getRawValue(), $input, '->getRawValue() returns the unescaped value');

    $this->assertEquals(Escaper::escape('entities', $output)->getTitle(), '&lt;strong&gt;escaped!&lt;/strong&gt;', '::escape() does not double escape an object');
    $this->assertTrue(Escaper::escape('entities', new \DirectoryIterator('.')) instanceof IteratorDecorator, '::escape() returns a IteratorDecorator object if the value to escape is an object that implements the ArrayAccess interface');
  }

  public function testEscapeDoesNotEscapeObjectMarkedAsBeingSafe()
  {
    $this->assertTrue(Escaper::escape('entities', new SafeDecorator(new OutputEscaperTestClass())) instanceof OutputEscaperTestClass, '::escape() returns the original value if it is marked as being safe');

    Escaper::markClassAsSafe('Symfony\Tests\Components\OutputEscaper\OutputEscaperTestClass');
    $this->assertTrue(Escaper::escape('entities', new OutputEscaperTestClass()) instanceof OutputEscaperTestClass, '::escape() returns the original value if the object class is marked as being safe');
    $this->assertTrue(Escaper::escape('entities', new OutputEscaperTestClassChild()) instanceof OutputEscaperTestClassChild, '::escape() returns the original value if one of the object parent class is marked as being safe');
  }

  public function testEscapeCannotEscapeResources()
  {
    $fh = fopen(__FILE__, 'r');
    try
    {
      Escaper::escape('entities', $fh);

      $this->fail('::escape() throws an InvalidArgumentException if the value cannot be escaped');
    }
    catch (\InvalidArgumentException $e)
    {
    }
    fclose($fh);
  }

  public function testUnescapeDoesNotUnescapeSpecialValues()
  {
    $this->assertTrue(Escaper::unescape(null) === null, '::unescape() returns null if the value to unescape is null');
    $this->assertTrue(Escaper::unescape(false) === false, '::unescape() returns false if the value to unescape is false');
    $this->assertTrue(Escaper::unescape(true) === true, '::unescape() returns true if the value to unescape is true');
  }

  public function testUnescapeUnescapesStrings()
  {
    $this->assertEquals(Escaper::unescape('&lt;strong&gt;escaped!&lt;/strong&gt;'), '<strong>escaped!</strong>', '::unescape() returns an unescaped string if the value to unescape is a string');
    $this->assertEquals(Escaper::unescape('&lt;strong&gt;&eacute;chapp&eacute;&lt;/strong&gt;'), '<strong>échappé</strong>', '::unescape() returns an unescaped string if the value to unescape is a string');
  }

  public function testUnescapeUnescapesArrays()
  {
    $input = Escaper::escape('entities', array(
      'foo' => '<strong>escaped!</strong>',
      'bar' => array('foo' => '<strong>escaped!</strong>'),
    ));
    $output = Escaper::unescape($input);
    $this->assertTrue(is_array($output), '::unescape() returns an array if the input is a ArrayDecorator object');
    $this->assertEquals($output['foo'], '<strong>escaped!</strong>', '::unescape() unescapes all elements of the original array');
    $this->assertEquals($output['bar']['foo'], '<strong>escaped!</strong>', '::unescape() is recursive');
  }

  public function testUnescapeUnescapesObjects()
  {
    $object = new OutputEscaperTestClass();
    $input = Escaper::escape('entities', $object);
    $output = Escaper::unescape($input);
    $this->assertTrue($output instanceof OutputEscaperTestClass, '::unescape() returns the original object when a ObjectDecorator object is passed');
    $this->assertEquals($output->getTitle(), '<strong>escaped!</strong>', '::unescape() unescapes all methods of the original object');
    $this->assertEquals($output->title, '<strong>escaped!</strong>', '::unescape() unescapes all properties of the original object');
    $this->assertEquals($output->getTitleTitle(), '<strong>escaped!</strong>', '::unescape() is recursive');

    $this->assertTrue(IteratorDecorator::unescape(Escaper::escape('entities', new \DirectoryIterator('.'))) instanceof \DirectoryIterator, '::unescape() unescapes IteratorDecorator objects');
  }

  public function testUnescapeDoesNotUnescapeObjectMarkedAsBeingSafe()
  {
    $this->assertTrue(Escaper::unescape(Escaper::escape('entities', new SafeDecorator(new OutputEscaperTestClass()))) instanceof OutputEscaperTestClass, '::unescape() returns the original value if it is marked as being safe');

    Escaper::markClassAsSafe('OutputEscaperTestClass');
    $this->assertTrue(Escaper::unescape(Escaper::escape('entities', new OutputEscaperTestClass())) instanceof OutputEscaperTestClass, '::unescape() returns the original value if the object class is marked as being safe');
    $this->assertTrue(Escaper::unescape(Escaper::escape('entities', new OutputEscaperTestClassChild())) instanceof OutputEscaperTestClassChild, '::unescape() returns the original value if one of the object parent class is marked as being safe');
  }

  public function testUnescapeDoesNothingToResources()
  {
    $fh = fopen(__FILE__, 'r');
    $this->assertEquals(Escaper::unescape($fh), $fh, '::unescape() do nothing to resources');
  }

  public function testUnescapeUnescapesMixedArrays()
  {
    $object = new OutputEscaperTestClass();
    $input = array(
      'foo'    => 'bar',
      'bar'    => Escaper::escape('entities', '<strong>bar</strong>'),
      'foobar' => Escaper::escape('entities', $object),
    );
    $output = array(
      'foo'    => 'bar',
      'bar'    => '<strong>bar</strong>',
      'foobar' => $object,
    );
    $this->assertEquals(Escaper::unescape($input), $output, '::unescape() unescapes values with some escaped and unescaped values');
  }
}

class OutputEscaperTestClass
{
  public $title = '<strong>escaped!</strong>';

  public function getTitle()
  {
    return $this->title;
  }

  public function getTitleTitle()
  {
    $o = new self;

    return $o->getTitle();
  }
}

class OutputEscaperTestClassChild extends OutputEscaperTestClass
{
}
