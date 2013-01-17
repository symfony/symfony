<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Input;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ArgvInputTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $_SERVER['argv'] = array('cli.php', 'foo');
        $input = new ArgvInput();
        $r = new \ReflectionObject($input);
        $p = $r->getProperty('tokens');
        $p->setAccessible(true);

        $this->assertEquals(array('foo'), $p->getValue($input), '__construct() automatically get its input from the argv server variable');
    }

    public function testParser()
    {
        $input = new ArgvInput(array('cli.php', 'foo'));
        $input->bind(new InputDefinition(array(new InputArgument('name'))));
        $this->assertEquals(array('name' => 'foo'), $input->getArguments(), '->parse() parses required arguments');

        $input->bind(new InputDefinition(array(new InputArgument('name'))));
        $this->assertEquals(array('name' => 'foo'), $input->getArguments(), '->parse() is stateless');

        $input = new ArgvInput(array('cli.php', '--foo'));
        $input->bind(new InputDefinition(array(new InputOption('foo'))));
        $this->assertEquals(array('foo' => true), $input->getOptions(), '->parse() parses long options without a value');

        $input = new ArgvInput(array('cli.php', '--foo=bar'));
        $input->bind(new InputDefinition(array(new InputOption('foo', 'f', InputOption::VALUE_REQUIRED))));
        $this->assertEquals(array('foo' => 'bar'), $input->getOptions(), '->parse() parses long options with a required value (with a = separator)');

        $input = new ArgvInput(array('cli.php', '--foo', 'bar'));
        $input->bind(new InputDefinition(array(new InputOption('foo', 'f', InputOption::VALUE_REQUIRED))));
        $this->assertEquals(array('foo' => 'bar'), $input->getOptions(), '->parse() parses long options with a required value (with a space separator)');

        try {
            $input = new ArgvInput(array('cli.php', '--foo'));
            $input->bind(new InputDefinition(array(new InputOption('foo', 'f', InputOption::VALUE_REQUIRED))));
            $this->fail('->parse() throws a \RuntimeException if no value is passed to an option when it is required');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\RuntimeException', $e, '->parse() throws a \RuntimeException if no value is passed to an option when it is required');
            $this->assertEquals('The "--foo" option requires a value.', $e->getMessage(), '->parse() throws a \RuntimeException if no value is passed to an option when it is required');
        }

        $input = new ArgvInput(array('cli.php', '-f'));
        $input->bind(new InputDefinition(array(new InputOption('foo', 'f'))));
        $this->assertEquals(array('foo' => true), $input->getOptions(), '->parse() parses short options without a value');

        $input = new ArgvInput(array('cli.php', '-fbar'));
        $input->bind(new InputDefinition(array(new InputOption('foo', 'f', InputOption::VALUE_REQUIRED))));
        $this->assertEquals(array('foo' => 'bar'), $input->getOptions(), '->parse() parses short options with a required value (with no separator)');

        $input = new ArgvInput(array('cli.php', '-f', 'bar'));
        $input->bind(new InputDefinition(array(new InputOption('foo', 'f', InputOption::VALUE_REQUIRED))));
        $this->assertEquals(array('foo' => 'bar'), $input->getOptions(), '->parse() parses short options with a required value (with a space separator)');

        $input = new ArgvInput(array('cli.php', '-f', ''));
        $input->bind(new InputDefinition(array(new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL))));
        $this->assertEquals(array('foo' => ''), $input->getOptions(), '->parse() parses short options with an optional empty value');

        $input = new ArgvInput(array('cli.php', '-f', '', 'foo'));
        $input->bind(new InputDefinition(array(new InputArgument('name'), new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL))));
        $this->assertEquals(array('foo' => ''), $input->getOptions(), '->parse() parses short options with an optional empty value followed by an argument');

        $input = new ArgvInput(array('cli.php', '-f', '', '-b'));
        $input->bind(new InputDefinition(array(new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL), new InputOption('bar', 'b'))));
        $this->assertEquals(array('foo' => '', 'bar' => true), $input->getOptions(), '->parse() parses short options with an optional empty value followed by an option');

        $input = new ArgvInput(array('cli.php', '-f', '-b', 'foo'));
        $input->bind(new InputDefinition(array(new InputArgument('name'), new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL), new InputOption('bar', 'b'))));
        $this->assertEquals(array('foo' => null, 'bar' => true), $input->getOptions(), '->parse() parses short options with an optional value which is not present');

        try {
            $input = new ArgvInput(array('cli.php', '-f'));
            $input->bind(new InputDefinition(array(new InputOption('foo', 'f', InputOption::VALUE_REQUIRED))));
            $this->fail('->parse() throws a \RuntimeException if no value is passed to an option when it is required');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\RuntimeException', $e, '->parse() throws a \RuntimeException if no value is passed to an option when it is required');
            $this->assertEquals('The "--foo" option requires a value.', $e->getMessage(), '->parse() throws a \RuntimeException if no value is passed to an option when it is required');
        }

        try {
            $input = new ArgvInput(array('cli.php', '-ffoo'));
            $input->bind(new InputDefinition(array(new InputOption('foo', 'f', InputOption::VALUE_NONE))));
            $this->fail('->parse() throws a \RuntimeException if a value is passed to an option which does not take one');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\RuntimeException', $e, '->parse() throws a \RuntimeException if a value is passed to an option which does not take one');
            $this->assertEquals('The "-o" option does not exist.', $e->getMessage(), '->parse() throws a \RuntimeException if a value is passed to an option which does not take one');
        }

        try {
            $input = new ArgvInput(array('cli.php', 'foo', 'bar'));
            $input->bind(new InputDefinition());
            $this->fail('->parse() throws a \RuntimeException if too many arguments are passed');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\RuntimeException', $e, '->parse() throws a \RuntimeException if too many arguments are passed');
            $this->assertEquals('Too many arguments.', $e->getMessage(), '->parse() throws a \RuntimeException if too many arguments are passed');
        }

        try {
            $input = new ArgvInput(array('cli.php', '--foo'));
            $input->bind(new InputDefinition());
            $this->fail('->parse() throws a \RuntimeException if an unknown long option is passed');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\RuntimeException', $e, '->parse() throws a \RuntimeException if an unknown long option is passed');
            $this->assertEquals('The "--foo" option does not exist.', $e->getMessage(), '->parse() throws a \RuntimeException if an unknown long option is passed');
        }

        try {
            $input = new ArgvInput(array('cli.php', '-f'));
            $input->bind(new InputDefinition());
            $this->fail('->parse() throws a \RuntimeException if an unknown short option is passed');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\RuntimeException', $e, '->parse() throws a \RuntimeException if an unknown short option is passed');
            $this->assertEquals('The "-f" option does not exist.', $e->getMessage(), '->parse() throws a \RuntimeException if an unknown short option is passed');
        }

        $input = new ArgvInput(array('cli.php', '-fb'));
        $input->bind(new InputDefinition(array(new InputOption('foo', 'f'), new InputOption('bar', 'b'))));
        $this->assertEquals(array('foo' => true, 'bar' => true), $input->getOptions(), '->parse() parses short options when they are aggregated as a single one');

        $input = new ArgvInput(array('cli.php', '-fb', 'bar'));
        $input->bind(new InputDefinition(array(new InputOption('foo', 'f'), new InputOption('bar', 'b', InputOption::VALUE_REQUIRED))));
        $this->assertEquals(array('foo' => true, 'bar' => 'bar'), $input->getOptions(), '->parse() parses short options when they are aggregated as a single one and the last one has a required value');

        $input = new ArgvInput(array('cli.php', '-fb', 'bar'));
        $input->bind(new InputDefinition(array(new InputOption('foo', 'f'), new InputOption('bar', 'b', InputOption::VALUE_OPTIONAL))));
        $this->assertEquals(array('foo' => true, 'bar' => 'bar'), $input->getOptions(), '->parse() parses short options when they are aggregated as a single one and the last one has an optional value');

        $input = new ArgvInput(array('cli.php', '-fbbar'));
        $input->bind(new InputDefinition(array(new InputOption('foo', 'f'), new InputOption('bar', 'b', InputOption::VALUE_OPTIONAL))));
        $this->assertEquals(array('foo' => true, 'bar' => 'bar'), $input->getOptions(), '->parse() parses short options when they are aggregated as a single one and the last one has an optional value with no separator');

        $input = new ArgvInput(array('cli.php', '-fbbar'));
        $input->bind(new InputDefinition(array(new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL), new InputOption('bar', 'b', InputOption::VALUE_OPTIONAL))));
        $this->assertEquals(array('foo' => 'bbar', 'bar' => null), $input->getOptions(), '->parse() parses short options when they are aggregated as a single one and one of them takes a value');

        try {
            $input = new ArgvInput(array('cli.php', 'foo', 'bar', 'baz', 'bat'));
            $input->bind(new InputDefinition(array(new InputArgument('name', InputArgument::IS_ARRAY))));
            $this->assertEquals(array('name' => array('foo', 'bar', 'baz', 'bat')), $input->getArguments(), '->parse() parses array arguments');
        } catch (\RuntimeException $e) {
            $this->assertNotEquals('Too many arguments.', $e->getMessage(), '->parse() parses array arguments');
        }

        $input = new ArgvInput(array('cli.php', '--name=foo', '--name=bar', '--name=baz'));
        $input->bind(new InputDefinition(array(new InputOption('name', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY))));
        $this->assertEquals(array('name' => array('foo', 'bar', 'baz')), $input->getOptions());

        try {
            $input = new ArgvInput(array('cli.php', '-1'));
            $input->bind(new InputDefinition(array(new InputArgument('number'))));
            $this->fail('->parse() throws a \RuntimeException if an unknown option is passed');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\RuntimeException', $e, '->parse() parses arguments with leading dashes as options without having encountered a double-dash sequence');
            $this->assertEquals('The "-1" option does not exist.', $e->getMessage(), '->parse() parses arguments with leading dashes as options without having encountered a double-dash sequence');
        }

        $input = new ArgvInput(array('cli.php', '--', '-1'));
        $input->bind(new InputDefinition(array(new InputArgument('number'))));
        $this->assertEquals(array('number' => '-1'), $input->getArguments(), '->parse() parses arguments with leading dashes as arguments after having encountered a double-dash sequence');

        $input = new ArgvInput(array('cli.php', '-f', 'bar', '--', '-1'));
        $input->bind(new InputDefinition(array(new InputArgument('number'), new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL))));
        $this->assertEquals(array('foo' => 'bar'), $input->getOptions(), '->parse() parses arguments with leading dashes as options before having encountered a double-dash sequence');
        $this->assertEquals(array('number' => '-1'), $input->getArguments(), '->parse() parses arguments with leading dashes as arguments after having encountered a double-dash sequence');

        $input = new ArgvInput(array('cli.php', '-f', 'bar', ''));
        $input->bind(new InputDefinition(array(new InputArgument('empty'), new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL))));
        $this->assertEquals(array('empty' => ''), $input->getArguments(), '->parse() parses empty string arguments');
    }

    public function testGetFirstArgument()
    {
        $input = new ArgvInput(array('cli.php', '-fbbar'));
        $this->assertEquals('', $input->getFirstArgument(), '->getFirstArgument() returns the first argument from the raw input');

        $input = new ArgvInput(array('cli.php', '-fbbar', 'foo'));
        $this->assertEquals('foo', $input->getFirstArgument(), '->getFirstArgument() returns the first argument from the raw input');
    }

    public function testHasParameterOption()
    {
        $input = new ArgvInput(array('cli.php', '-f', 'foo'));
        $this->assertTrue($input->hasParameterOption('-f'), '->hasParameterOption() returns true if the given short option is in the raw input');

        $input = new ArgvInput(array('cli.php', '--foo', 'foo'));
        $this->assertTrue($input->hasParameterOption('--foo'), '->hasParameterOption() returns true if the given short option is in the raw input');

        $input = new ArgvInput(array('cli.php', 'foo'));
        $this->assertFalse($input->hasParameterOption('--foo'), '->hasParameterOption() returns false if the given short option is not in the raw input');
    }

    /**
     * @dataProvider provideGetParameterOptionValues
     */
    public function testGetParameterOptionEqualSign($argv, $key, $expected)
    {
        $input = new ArgvInput($argv);
        $this->assertEquals($expected, $input->getParameterOption($key), '->getParameterOption() returns the expected value');
    }

    public function provideGetParameterOptionValues()
    {
        return array(
            array(array('app/console', 'foo:bar', '-e', 'dev'), '-e', 'dev'),
            array(array('app/console', 'foo:bar', '--env=dev'), '--env', 'dev'),
            array(array('app/console', 'foo:bar', '-e', 'dev'), array('-e', '--env'), 'dev'),
            array(array('app/console', 'foo:bar', '--env=dev'), array('-e', '--env'), 'dev'),
        );
    }
}
