<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Console\Output;

use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class OutputTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $output = new TestOutput(Output::VERBOSITY_QUIET, true);
        $this->assertEquals(Output::VERBOSITY_QUIET, $output->getVerbosity(), '__construct() takes the verbosity as its first argument');
        $this->assertTrue($output->isDecorated(), '__construct() takes the decorated flag as its second argument');
    }

    public function testSetIsDecorated()
    {
        $output = new TestOutput();
        $output->setDecorated(true);
        $this->assertTrue($output->isDecorated(), 'setDecorated() sets the decorated flag');
    }

    public function testSetGetVerbosity()
    {
        $output = new TestOutput();
        $output->setVerbosity(Output::VERBOSITY_QUIET);
        $this->assertEquals(Output::VERBOSITY_QUIET, $output->getVerbosity(), '->setVerbosity() sets the verbosity');
    }

    public function testWrite()
    {
        $fooStyle = new OutputFormatterStyle('yellow', 'red', array('blink'));
        $output = new TestOutput(Output::VERBOSITY_QUIET);
        $output->writeln('foo');
        $this->assertEquals('', $output->output, '->writeln() outputs nothing if verbosity is set to VERBOSITY_QUIET');

        $output = new TestOutput();
        $output->writeln(array('foo', 'bar'));
        $this->assertEquals("foo\nbar\n", $output->output, '->writeln() can take an array of messages to output');

        $output = new TestOutput();
        $output->writeln('<info>foo</info>', Output::OUTPUT_RAW);
        $this->assertEquals("<info>foo</info>\n", $output->output, '->writeln() outputs the raw message if OUTPUT_RAW is specified');

        $output = new TestOutput();
        $output->writeln('<info>foo</info>', Output::OUTPUT_PLAIN);
        $this->assertEquals("foo\n", $output->output, '->writeln() strips decoration tags if OUTPUT_PLAIN is specified');

        $output = new TestOutput();
        $output->setDecorated(false);
        $output->writeln('<info>foo</info>');
        $this->assertEquals("foo\n", $output->output, '->writeln() strips decoration tags if decoration is set to false');

        $output = new TestOutput();
        $output->getFormatter()->setStyle('FOO', $fooStyle);
        $output->setDecorated(true);
        $output->writeln('<foo>foo</foo>');
        $this->assertEquals("\033[33;41;5mfoo\033[0m\n", $output->output, '->writeln() decorates the output');

        try {
            $output->writeln('<foo>foo</foo>', 24);
            $this->fail('->writeln() throws an \InvalidArgumentException when the type does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->writeln() throws an \InvalidArgumentException when the type does not exist');
            $this->assertEquals('Unknown output type given (24)', $e->getMessage());
        }

        $output->clear();
        $output->write('<bar>foo</bar>');
        $this->assertEquals('<bar>foo</bar>', $output->output, '->write() do nothing when a style does not exist');

        $output->clear();
        $output->writeln('<bar>foo</bar>');
        $this->assertEquals("<bar>foo</bar>\n", $output->output, '->writeln() do nothing when a style does not exist');
    }
}

class TestOutput extends Output
{
    public $output = '';

    public function clear()
    {
        $this->output = '';
    }

    public function doWrite($message, $newline)
    {
        $this->output .= $message.($newline ? "\n" : '');
    }
}
