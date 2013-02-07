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

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ArrayInputTest extends \PHPUnit_Framework_TestCase
{
    public function testGetFirstArgument()
    {
        $input = new ArrayInput(array());
        $this->assertNull($input->getFirstArgument(), '->getFirstArgument() returns null if no argument were passed');
        $input = new ArrayInput(array('name' => 'Fabien'));
        $this->assertEquals('Fabien', $input->getFirstArgument(), '->getFirstArgument() returns the first passed argument');
        $input = new ArrayInput(array('--foo' => 'bar', 'name' => 'Fabien'));
        $this->assertEquals('Fabien', $input->getFirstArgument(), '->getFirstArgument() returns the first passed argument');
    }

    public function testHasParameterOption()
    {
        $input = new ArrayInput(array('name' => 'Fabien', '--foo' => 'bar'));
        $this->assertTrue($input->hasParameterOption('--foo'), '->hasParameterOption() returns true if an option is present in the passed parameters');
        $this->assertFalse($input->hasParameterOption('--bar'), '->hasParameterOption() returns false if an option is not present in the passed parameters');

        $input = new ArrayInput(array('--foo'));
        $this->assertTrue($input->hasParameterOption('--foo'), '->hasParameterOption() returns true if an option is present in the passed parameters');
    }

    public function testParse()
    {
        $input = new ArrayInput(array('name' => 'foo'), new InputDefinition(array(new InputArgument('name'))));
        $this->assertEquals(array('name' => 'foo'), $input->getArguments(), '->parse() parses required arguments');

        try {
            $input = new ArrayInput(array('foo' => 'foo'), new InputDefinition(array(new InputArgument('name'))));
            $this->fail('->parse() throws an \InvalidArgumentException exception if an invalid argument is passed');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->parse() throws an \InvalidArgumentException exception if an invalid argument is passed');
            $this->assertEquals('The "foo" argument does not exist.', $e->getMessage(), '->parse() throws an \InvalidArgumentException exception if an invalid argument is passed');
        }

        $input = new ArrayInput(array('--foo' => 'bar'), new InputDefinition(array(new InputOption('foo'))));
        $this->assertEquals(array('foo' => 'bar'), $input->getOptions(), '->parse() parses long options');

        $input = new ArrayInput(array('--foo' => 'bar'), new InputDefinition(array(new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL, '', 'default'))));
        $this->assertEquals(array('foo' => 'bar'), $input->getOptions(), '->parse() parses long options with a default value');

        $input = new ArrayInput(array('--foo' => null), new InputDefinition(array(new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL, '', 'default'))));
        $this->assertEquals(array('foo' => 'default'), $input->getOptions(), '->parse() parses long options with a default value');

        try {
            $input = new ArrayInput(array('--foo' => null), new InputDefinition(array(new InputOption('foo', 'f', InputOption::VALUE_REQUIRED))));
            $this->fail('->parse() throws an \InvalidArgumentException exception if a required option is passed without a value');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->parse() throws an \InvalidArgumentException exception if a required option is passed without a value');
            $this->assertEquals('The "--foo" option requires a value.', $e->getMessage(), '->parse() throws an \InvalidArgumentException exception if a required option is passed without a value');
        }

        try {
            $input = new ArrayInput(array('--foo' => 'foo'), new InputDefinition());
            $this->fail('->parse() throws an \InvalidArgumentException exception if an invalid option is passed');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->parse() throws an \InvalidArgumentException exception if an invalid option is passed');
            $this->assertEquals('The "--foo" option does not exist.', $e->getMessage(), '->parse() throws an \InvalidArgumentException exception if an invalid option is passed');
        }

        $input = new ArrayInput(array('-f' => 'bar'), new InputDefinition(array(new InputOption('foo', 'f'))));
        $this->assertEquals(array('foo' => 'bar'), $input->getOptions(), '->parse() parses short options');

        try {
            $input = new ArrayInput(array('-o' => 'foo'), new InputDefinition());
            $this->fail('->parse() throws an \InvalidArgumentException exception if an invalid option is passed');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->parse() throws an \InvalidArgumentException exception if an invalid option is passed');
            $this->assertEquals('The "-o" option does not exist.', $e->getMessage(), '->parse() throws an \InvalidArgumentException exception if an invalid option is passed');
        }
    }
}
