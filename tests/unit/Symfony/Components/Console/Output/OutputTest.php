<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\Console\Output\Output;

$t = new LimeTest(13);

class TestOutput extends Output
{
  public $output = '';

  static public function getStyle($name)
  {
    return static::$styles[$name];
  }

  public function doWrite($message)
  {
    $this->output .= $message."\n";
  }
}

// __construct()
$t->diag('__construct()');
$output = new TestOutput(Output::VERBOSITY_QUIET, true);
$t->is($output->getVerbosity(), Output::VERBOSITY_QUIET, '__construct() takes the verbosity as its first argument');
$t->is($output->isDecorated(), true, '__construct() takes the decorated flag as its second argument');

// ->setDecorated() ->isDecorated()
$t->diag('->setDecorated() ->isDecorated()');
$output = new TestOutput();
$output->setDecorated(true);
$t->is($output->isDecorated(), true, 'setDecorated() sets the decorated flag');

// ->setVerbosity() ->getVerbosity()
$t->diag('->setVerbosity() ->getVerbosity()');
$output->setVerbosity(Output::VERBOSITY_QUIET);
$t->is($output->getVerbosity(), Output::VERBOSITY_QUIET, '->setVerbosity() sets the verbosity');

// ::setStyle()
$t->diag('::setStyle()');
Output::setStyle('FOO', array('bg' => 'red', 'fg' => 'yellow', 'blink' => true));
$t->is(TestOutput::getStyle('foo'), array('bg' => 'red', 'fg' => 'yellow', 'blink' => true), '::setStyle() sets a new style');

// ->write()
$t->diag('->write()');
$output = new TestOutput(Output::VERBOSITY_QUIET);
$output->write('foo');
$t->is($output->output, '', '->write() outputs nothing if verbosity is set to VERBOSITY_QUIET');

$output = new TestOutput();
$output->write(array('foo', 'bar'));
$t->is($output->output, "foo\nbar\n", '->write() can take an array of messages to output');

$output = new TestOutput();
$output->write('<info>foo</info>', Output::OUTPUT_RAW);
$t->is($output->output, "<info>foo</info>\n", '->write() outputs the raw message if OUTPUT_RAW is specified');

$output = new TestOutput();
$output->write('<info>foo</info>', Output::OUTPUT_PLAIN);
$t->is($output->output, "foo\n", '->write() strips decoration tags if OUTPUT_PLAIN is specified');

$output = new TestOutput();
$output->setDecorated(false);
$output->write('<info>foo</info>');
$t->is($output->output, "foo\n", '->write() strips decoration tags if decoration is set to false');

$output = new TestOutput();
$output->setDecorated(true);
$output->write('<foo>foo</foo>');
$t->is($output->output, "\033[33;41;5mfoo\033[0m\n", '->write() decorates the output');

try
{
  $output->write('<foo>foo</foo>', 24);
  $t->fail('->write() throws an \InvalidArgumentException when the type does not exist');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->write() throws an \InvalidArgumentException when the type does not exist');
}

try
{
  $output->write('<bar>foo</bar>');
  $t->fail('->write() throws an \InvalidArgumentException when a style does not exist');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->write() throws an \InvalidArgumentException when a style does not exist');
}
