<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Console\Input;

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\Console\Input\ArrayInput;
use Symfony\Components\Console\Input\InputDefinition;
use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;

class ArrayInputTest extends \PHPUnit_Framework_TestCase
{
  public function testGetFirstArgument()
  {
    $input = new ArrayInput(array());
    $this->assertEquals($input->getFirstArgument(), null, '->getFirstArgument() returns null if no argument were passed');
    $input = new ArrayInput(array('name' => 'Fabien'));
    $this->assertEquals($input->getFirstArgument(), 'Fabien', '->getFirstArgument() returns the first passed argument');
    $input = new ArrayInput(array('--foo' => 'bar', 'name' => 'Fabien'));
    $this->assertEquals($input->getFirstArgument(), 'Fabien', '->getFirstArgument() returns the first passed argument');
  }

  public function testHasParameterOption()
  {
    $input = new ArrayInput(array('name' => 'Fabien', '--foo' => 'bar'));
    $this->assertTrue($input->hasParameterOption('--foo'), '->hasParameterOption() returns true if an option is present in the passed parameters');
    $this->assertTrue(!$input->hasParameterOption('--bar'), '->hasParameterOption() returns false if an option is not present in the passed parameters');

    $input = new ArrayInput(array('--foo'));
    $this->assertTrue($input->hasParameterOption('--foo'), '->hasParameterOption() returns true if an option is present in the passed parameters');
  }

  public function testParse()
  {
    $input = new ArrayInput(array('name' => 'foo'), new InputDefinition(array(new InputArgument('name'))));
    $this->assertEquals($input->getArguments(), array('name' => 'foo'), '->parse() parses required arguments');

    try
    {
      $input = new ArrayInput(array('foo' => 'foo'), new InputDefinition(array(new InputArgument('name'))));
      $this->fail('->parse() throws an \InvalidArgumentException exception if an invalid argument is passed');
    }
    catch (\RuntimeException $e)
    {
    }

    $input = new ArrayInput(array('--foo' => 'bar'), new InputDefinition(array(new InputOption('foo'))));
    $this->assertEquals($input->getOptions(), array('foo' => 'bar'), '->parse() parses long options');

    $input = new ArrayInput(array('--foo' => 'bar'), new InputDefinition(array(new InputOption('foo', 'f', InputOption::PARAMETER_OPTIONAL, '', 'default'))));
    $this->assertEquals($input->getOptions(), array('foo' => 'bar'), '->parse() parses long options with a default value');

    $input = new ArrayInput(array('--foo' => null), new InputDefinition(array(new InputOption('foo', 'f', InputOption::PARAMETER_OPTIONAL, '', 'default'))));
    $this->assertEquals($input->getOptions(), array('foo' => 'default'), '->parse() parses long options with a default value');

    try
    {
      $input = new ArrayInput(array('--foo' => null), new InputDefinition(array(new InputOption('foo', 'f', InputOption::PARAMETER_REQUIRED))));
      $this->fail('->parse() throws an \InvalidArgumentException exception if a required option is passed without a value');
    }
    catch (\RuntimeException $e)
    {
    }

    try
    {
      $input = new ArrayInput(array('--foo' => 'foo'), new InputDefinition());
      $this->fail('->parse() throws an \InvalidArgumentException exception if an invalid option is passed');
    }
    catch (\RuntimeException $e)
    {
    }

    $input = new ArrayInput(array('-f' => 'bar'), new InputDefinition(array(new InputOption('foo', 'f'))));
    $this->assertEquals($input->getOptions(), array('foo' => 'bar'), '->parse() parses short options');

    try
    {
      $input = new ArrayInput(array('-o' => 'foo'), new InputDefinition());
      $this->fail('->parse() throws an \InvalidArgumentException exception if an invalid option is passed');
    }
    catch (\RuntimeException $e)
    {
    }
  }
}
