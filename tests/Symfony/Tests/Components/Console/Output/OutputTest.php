<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Console\Output;

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\Console\Output\Output;

class OutputTest extends \PHPUnit_Framework_TestCase
{
  public function testConstructor()
  {
    $output = new TestOutput(Output::VERBOSITY_QUIET, true);
    $this->assertEquals($output->getVerbosity(), Output::VERBOSITY_QUIET, '__construct() takes the verbosity as its first argument');
    $this->assertEquals($output->isDecorated(), true, '__construct() takes the decorated flag as its second argument');
  }

  public function testSetIsDecorated()
  {
    $output = new TestOutput();
    $output->setDecorated(true);
    $this->assertEquals($output->isDecorated(), true, 'setDecorated() sets the decorated flag');
  }

  public function testSetGetVerbosity()
  {
    $output = new TestOutput();
    $output->setVerbosity(Output::VERBOSITY_QUIET);
    $this->assertEquals($output->getVerbosity(), Output::VERBOSITY_QUIET, '->setVerbosity() sets the verbosity');
  }

  public function testSetStyle()
  {
    Output::setStyle('FOO', array('bg' => 'red', 'fg' => 'yellow', 'blink' => true));
    $this->assertEquals(TestOutput::getStyle('foo'), array('bg' => 'red', 'fg' => 'yellow', 'blink' => true), '::setStyle() sets a new style');
  }

  public function testWrite()
  {
    $output = new TestOutput(Output::VERBOSITY_QUIET);
    $output->writeln('foo');
    $this->assertEquals($output->output, '', '->writeln() outputs nothing if verbosity is set to VERBOSITY_QUIET');

    $output = new TestOutput();
    $output->writeln(array('foo', 'bar'));
    $this->assertEquals($output->output, "foo\nbar\n", '->writeln() can take an array of messages to output');

    $output = new TestOutput();
    $output->writeln('<info>foo</info>', Output::OUTPUT_RAW);
    $this->assertEquals($output->output, "<info>foo</info>\n", '->writeln() outputs the raw message if OUTPUT_RAW is specified');

    $output = new TestOutput();
    $output->writeln('<info>foo</info>', Output::OUTPUT_PLAIN);
    $this->assertEquals($output->output, "foo\n", '->writeln() strips decoration tags if OUTPUT_PLAIN is specified');

    $output = new TestOutput();
    $output->setDecorated(false);
    $output->writeln('<info>foo</info>');
    $this->assertEquals($output->output, "foo\n", '->writeln() strips decoration tags if decoration is set to false');

    $output = new TestOutput();
    $output->setDecorated(true);
    $output->writeln('<foo>foo</foo>');
    $this->assertEquals($output->output, "\033[33;41;5mfoo\033[0m\n", '->writeln() decorates the output');

    try
    {
      $output->writeln('<foo>foo</foo>', 24);
      $this->fail('->writeln() throws an \InvalidArgumentException when the type does not exist');
    }
    catch (\InvalidArgumentException $e)
    {
    }

    try
    {
      $output->writeln('<bar>foo</bar>');
      $this->fail('->writeln() throws an \InvalidArgumentException when a style does not exist');
    }
    catch (\InvalidArgumentException $e)
    {
    }
  }
}

class TestOutput extends Output
{
  public $output = '';

  static public function getStyle($name)
  {
    return static::$styles[$name];
  }

  public function doWrite($message, $newline)
  {
    $this->output .= $message.($newline ? "\n" : '');
  }
}
