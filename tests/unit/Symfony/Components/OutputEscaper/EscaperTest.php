<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\OutputEscaper\Escaper;
use Symfony\Components\OutputEscaper\Safe;
use Symfony\Components\OutputEscaper\IteratorDecorator;
use Symfony\Components\OutputEscaper\ArrayDecorator;
use Symfony\Components\OutputEscaper\ObjectDecorator;

$t = new LimeTest(39);

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

// ::escape()
$t->diag('::escape()');
$t->diag('::escape() does not escape special values');
$t->ok(Escaper::escape('esc_entities', null) === null, '::escape() returns null if the value to escape is null');
$t->ok(Escaper::escape('esc_entities', false) === false, '::escape() returns false if the value to escape is false');
$t->ok(Escaper::escape('esc_entities', true) === true, '::escape() returns true if the value to escape is true');

$t->diag('::escape() does not escape a value when escaping method is ESC_RAW');
$t->is(Escaper::escape('esc_raw', '<strong>escaped!</strong>'), '<strong>escaped!</strong>', '::escape() takes an escaping strategy function name as its first argument');

$t->diag('::escape() escapes strings');
$t->is(Escaper::escape('esc_entities', '<strong>escaped!</strong>'), '&lt;strong&gt;escaped!&lt;/strong&gt;', '::escape() returns an escaped string if the value to escape is a string');
$t->is(Escaper::escape('esc_entities', '<strong>échappé</strong>'), '&lt;strong&gt;&eacute;chapp&eacute;&lt;/strong&gt;', '::escape() returns an escaped string if the value to escape is a string');

$t->diag('::escape() escapes arrays');
$input = array(
  'foo' => '<strong>escaped!</strong>',
  'bar' => array('foo' => '<strong>escaped!</strong>'),
);
$output = Escaper::escape('esc_entities', $input);
$t->ok($output instanceof ArrayDecorator, '::escape() returns a ArrayDecorator object if the value to escape is an array');
$t->is($output['foo'], '&lt;strong&gt;escaped!&lt;/strong&gt;', '::escape() escapes all elements of the original array');
$t->is($output['bar']['foo'], '&lt;strong&gt;escaped!&lt;/strong&gt;', '::escape() is recursive');
$t->is($output->getRawValue(), $input, '->getRawValue() returns the unescaped value');

$t->diag('::escape() escapes objects');
$input = new OutputEscaperTestClass();
$output = Escaper::escape('esc_entities', $input);
$t->ok($output instanceof ObjectDecorator, '::escape() returns a ObjectDecorator object if the value to escape is an object');
$t->is($output->getTitle(), '&lt;strong&gt;escaped!&lt;/strong&gt;', '::escape() escapes all methods of the original object');
$t->is($output->title, '&lt;strong&gt;escaped!&lt;/strong&gt;', '::escape() escapes all properties of the original object');
$t->is($output->getTitleTitle(), '&lt;strong&gt;escaped!&lt;/strong&gt;', '::escape() is recursive');
$t->is($output->getRawValue(), $input, '->getRawValue() returns the unescaped value');

$t->is(Escaper::escape('esc_entities', $output)->getTitle(), '&lt;strong&gt;escaped!&lt;/strong&gt;', '::escape() does not double escape an object');
$t->ok(Escaper::escape('esc_entities', new \DirectoryIterator('.')) instanceof IteratorDecorator, '::escape() returns a IteratorDecorator object if the value to escape is an object that implements the ArrayAccess interface');

$t->diag('::escape() does not escape object marked as being safe');
$t->ok(Escaper::escape('esc_entities', new Safe(new OutputEscaperTestClass())) instanceof OutputEscaperTestClass, '::escape() returns the original value if it is marked as being safe');

Escaper::markClassAsSafe('OutputEscaperTestClass');
$t->ok(Escaper::escape('esc_entities', new OutputEscaperTestClass()) instanceof OutputEscaperTestClass, '::escape() returns the original value if the object class is marked as being safe');
$t->ok(Escaper::escape('esc_entities', new OutputEscaperTestClassChild()) instanceof OutputEscaperTestClassChild, '::escape() returns the original value if one of the object parent class is marked as being safe');

$t->diag('::escape() cannot escape resources');
$fh = fopen(__FILE__, 'r');
try
{
  Escaper::escape('esc_entities', $fh);
  $t->fail('::escape() throws an InvalidArgumentException if the value cannot be escaped');
}
catch (InvalidArgumentException $e)
{
  $t->pass('::escape() throws an InvalidArgumentException if the value cannot be escaped');
}

// ::unescape()
$t->diag('::unescape()');
$t->diag('::unescape() does not unescape special values');
$t->ok(Escaper::unescape(null) === null, '::unescape() returns null if the value to unescape is null');
$t->ok(Escaper::unescape(false) === false, '::unescape() returns false if the value to unescape is false');
$t->ok(Escaper::unescape(true) === true, '::unescape() returns true if the value to unescape is true');

$t->diag('::unescape() unescapes strings');
$t->is(Escaper::unescape('&lt;strong&gt;escaped!&lt;/strong&gt;'), '<strong>escaped!</strong>', '::unescape() returns an unescaped string if the value to unescape is a string');
$t->is(Escaper::unescape('&lt;strong&gt;&eacute;chapp&eacute;&lt;/strong&gt;'), '<strong>échappé</strong>', '::unescape() returns an unescaped string if the value to unescape is a string');

$t->diag('::unescape() unescapes arrays');
$input = Escaper::escape('esc_entities', array(
  'foo' => '<strong>escaped!</strong>',
  'bar' => array('foo' => '<strong>escaped!</strong>'),
));
$output = Escaper::unescape($input);
$t->ok(is_array($output), '::unescape() returns an array if the input is a ArrayDecorator object');
$t->is($output['foo'], '<strong>escaped!</strong>', '::unescape() unescapes all elements of the original array');
$t->is($output['bar']['foo'], '<strong>escaped!</strong>', '::unescape() is recursive');

$t->diag('::unescape() unescapes objects');
$object = new OutputEscaperTestClass();
$input = Escaper::escape('esc_entities', $object);
$output = Escaper::unescape($input);
$t->ok($output instanceof OutputEscaperTestClass, '::unescape() returns the original object when a ObjectDecorator object is passed');
$t->is($output->getTitle(), '<strong>escaped!</strong>', '::unescape() unescapes all methods of the original object');
$t->is($output->title, '<strong>escaped!</strong>', '::unescape() unescapes all properties of the original object');
$t->is($output->getTitleTitle(), '<strong>escaped!</strong>', '::unescape() is recursive');

$t->ok(IteratorDecorator::unescape(Escaper::escape('esc_entities', new DirectoryIterator('.'))) instanceof DirectoryIterator, '::unescape() unescapes IteratorDecorator objects');

$t->diag('::unescape() does not unescape object marked as being safe');
$t->ok(Escaper::unescape(Escaper::escape('esc_entities', new Safe(new OutputEscaperTestClass()))) instanceof OutputEscaperTestClass, '::unescape() returns the original value if it is marked as being safe');

Escaper::markClassAsSafe('OutputEscaperTestClass');
$t->ok(Escaper::unescape(Escaper::escape('esc_entities', new OutputEscaperTestClass())) instanceof OutputEscaperTestClass, '::unescape() returns the original value if the object class is marked as being safe');
$t->ok(Escaper::unescape(Escaper::escape('esc_entities', new OutputEscaperTestClassChild())) instanceof OutputEscaperTestClassChild, '::unescape() returns the original value if one of the object parent class is marked as being safe');

$t->diag('::unescape() do nothing to resources');
$fh = fopen(__FILE__, 'r');
$t->is(Escaper::unescape($fh), $fh, '::unescape() do nothing to resources');

$t->diag('::unescape() unescapes mixed arrays');
$object = new OutputEscaperTestClass();
$input = array(
  'foo'    => 'bar',
  'bar'    => Escaper::escape('esc_entities', '<strong>bar</strong>'),
  'foobar' => Escaper::escape('esc_entities', $object),
);
$output = array(
  'foo'    => 'bar',
  'bar'    => '<strong>bar</strong>',
  'foobar' => $object,
);
$t->is(Escaper::unescape($input), $output, '::unescape() unescapes values with some escaped and unescaped values');
