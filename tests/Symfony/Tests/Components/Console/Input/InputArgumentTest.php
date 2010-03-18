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

use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Exception;

class InputArgumentTest extends \PHPUnit_Framework_TestCase
{
  public function testConstructor()
  {
    $argument = new InputArgument('foo');
    $this->assertEquals($argument->getName(), 'foo', '__construct() takes a name as its first argument');

    // mode argument
    $argument = new InputArgument('foo');
    $this->assertEquals($argument->isRequired(), false, '__construct() gives a "Argument::OPTIONAL" mode by default');

    $argument = new InputArgument('foo', null);
    $this->assertEquals($argument->isRequired(), false, '__construct() can take "Argument::OPTIONAL" as its mode');

    $argument = new InputArgument('foo', InputArgument::OPTIONAL);
    $this->assertEquals($argument->isRequired(), false, '__construct() can take "Argument::PARAMETER_OPTIONAL" as its mode');

    $argument = new InputArgument('foo', InputArgument::REQUIRED);
    $this->assertEquals($argument->isRequired(), true, '__construct() can take "Argument::PARAMETER_REQUIRED" as its mode');

    try
    {
      $argument = new InputArgument('foo', 'ANOTHER_ONE');
      $this->fail('__construct() throws an Exception if the mode is not valid');
    }
    catch (\Exception $e)
    {
    }
  }

  public function testIsArray()
  {
    $argument = new InputArgument('foo', InputArgument::IS_ARRAY);
    $this->assertTrue($argument->isArray(), '->isArray() returns true if the argument can be an array');
    $argument = new InputArgument('foo', InputArgument::OPTIONAL | InputArgument::IS_ARRAY);
    $this->assertTrue($argument->isArray(), '->isArray() returns true if the argument can be an array');
    $argument = new InputArgument('foo', InputArgument::OPTIONAL);
    $this->assertTrue(!$argument->isArray(), '->isArray() returns false if the argument can not be an array');
  }

  public function testGetDescription()
  {
    $argument = new InputArgument('foo', null, 'Some description');
    $this->assertEquals($argument->getDescription(), 'Some description', '->getDescription() return the message description');
  }

  public function testGetDefault()
  {
    $argument = new InputArgument('foo', InputArgument::OPTIONAL, '', 'default');
    $this->assertEquals($argument->getDefault(), 'default', '->getDefault() return the default value');
  }

  public function testSetDefault()
  {
    $argument = new InputArgument('foo', InputArgument::OPTIONAL, '', 'default');
    $argument->setDefault(null);
    $this->assertTrue(is_null($argument->getDefault()), '->setDefault() can reset the default value by passing null');
    $argument->setDefault('another');
    $this->assertEquals($argument->getDefault(), 'another', '->setDefault() changes the default value');

    $argument = new InputArgument('foo', InputArgument::OPTIONAL | InputArgument::IS_ARRAY);
    $argument->setDefault(array(1, 2));
    $this->assertEquals($argument->getDefault(), array(1, 2), '->setDefault() changes the default value');

    try
    {
      $argument = new InputArgument('foo', InputArgument::REQUIRED);
      $argument->setDefault('default');
      $this->fail('->setDefault() throws an Exception if you give a default value for a required argument');
    }
    catch (\Exception $e)
    {
    }

    try
    {
      $argument = new InputArgument('foo', InputArgument::IS_ARRAY);
      $argument->setDefault('default');
      $this->fail('->setDefault() throws an Exception if you give a default value which is not an array for a IS_ARRAY option');
    }
    catch (\Exception $e)
    {
    }
  }
}
