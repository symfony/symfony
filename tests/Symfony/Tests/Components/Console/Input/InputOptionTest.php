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

use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Exception;

class InputOptionTest extends \PHPUnit_Framework_TestCase
{
  public function testConstructor()
  {
    $option = new InputOption('foo');
    $this->assertEquals($option->getName(), 'foo', '__construct() takes a name as its first argument');
    $option = new InputOption('--foo');
    $this->assertEquals($option->getName(), 'foo', '__construct() removes the leading -- of the option name');

    try
    {
      $option = new InputOption('foo', 'f', InputOption::PARAMETER_IS_ARRAY);
      $this->fail('->setDefault() throws an Exception if PARAMETER_IS_ARRAY option is used when an option does not accept a value');
    }
    catch (\Exception $e)
    {
    }

    // shortcut argument
    $option = new InputOption('foo', 'f');
    $this->assertEquals($option->getShortcut(), 'f', '__construct() can take a shortcut as its second argument');
    $option = new InputOption('foo', '-f');
    $this->assertEquals($option->getShortcut(), 'f', '__construct() removes the leading - of the shortcut');

    // mode argument
    $option = new InputOption('foo', 'f');
    $this->assertEquals($option->acceptParameter(), false, '__construct() gives a "Option::PARAMETER_NONE" mode by default');
    $this->assertEquals($option->isParameterRequired(), false, '__construct() gives a "Option::PARAMETER_NONE" mode by default');
    $this->assertEquals($option->isParameterOptional(), false, '__construct() gives a "Option::PARAMETER_NONE" mode by default');

    $option = new InputOption('foo', 'f', null);
    $this->assertEquals($option->acceptParameter(), false, '__construct() can take "Option::PARAMETER_NONE" as its mode');
    $this->assertEquals($option->isParameterRequired(), false, '__construct() can take "Option::PARAMETER_NONE" as its mode');
    $this->assertEquals($option->isParameterOptional(), false, '__construct() can take "Option::PARAMETER_NONE" as its mode');

    $option = new InputOption('foo', 'f', InputOption::PARAMETER_NONE);
    $this->assertEquals($option->acceptParameter(), false, '__construct() can take "Option::PARAMETER_NONE" as its mode');
    $this->assertEquals($option->isParameterRequired(), false, '__construct() can take "Option::PARAMETER_NONE" as its mode');
    $this->assertEquals($option->isParameterOptional(), false, '__construct() can take "Option::PARAMETER_NONE" as its mode');

    $option = new InputOption('foo', 'f', InputOption::PARAMETER_REQUIRED);
    $this->assertEquals($option->acceptParameter(), true, '__construct() can take "Option::PARAMETER_REQUIRED" as its mode');
    $this->assertEquals($option->isParameterRequired(), true, '__construct() can take "Option::PARAMETER_REQUIRED" as its mode');
    $this->assertEquals($option->isParameterOptional(), false, '__construct() can take "Option::PARAMETER_REQUIRED" as its mode');

    $option = new InputOption('foo', 'f', InputOption::PARAMETER_OPTIONAL);
    $this->assertEquals($option->acceptParameter(), true, '__construct() can take "Option::PARAMETER_OPTIONAL" as its mode');
    $this->assertEquals($option->isParameterRequired(), false, '__construct() can take "Option::PARAMETER_OPTIONAL" as its mode');
    $this->assertEquals($option->isParameterOptional(), true, '__construct() can take "Option::PARAMETER_OPTIONAL" as its mode');

    try
    {
      $option = new InputOption('foo', 'f', 'ANOTHER_ONE');
      $this->fail('__construct() throws an Exception if the mode is not valid');
    }
    catch (\Exception $e)
    {
    }
  }

  public function testIsArray()
  {
    $option = new InputOption('foo', null, InputOption::PARAMETER_OPTIONAL | InputOption::PARAMETER_IS_ARRAY);
    $this->assertTrue($option->isArray(), '->isArray() returns true if the option can be an array');
    $option = new InputOption('foo', null, InputOption::PARAMETER_NONE);
    $this->assertTrue(!$option->isArray(), '->isArray() returns false if the option can not be an array');
  }

  public function testGetDescription()
  {
    $option = new InputOption('foo', 'f', null, 'Some description');
    $this->assertEquals($option->getDescription(), 'Some description', '->getDescription() returns the description message');
  }

  public function testGetDefault()
  {
    $option = new InputOption('foo', null, InputOption::PARAMETER_OPTIONAL, '', 'default');
    $this->assertEquals($option->getDefault(), 'default', '->getDefault() returns the default value');

    $option = new InputOption('foo', null, InputOption::PARAMETER_REQUIRED, '', 'default');
    $this->assertEquals($option->getDefault(), 'default', '->getDefault() returns the default value');

    $option = new InputOption('foo', null, InputOption::PARAMETER_REQUIRED);
    $this->assertTrue(is_null($option->getDefault()), '->getDefault() returns null if no default value is configured');

    $option = new InputOption('foo', null, InputOption::PARAMETER_OPTIONAL | InputOption::PARAMETER_IS_ARRAY);
    $this->assertEquals($option->getDefault(), array(), '->getDefault() returns an empty array if option is an array');

    $option = new InputOption('foo', null, InputOption::PARAMETER_NONE);
    $this->assertTrue($option->getDefault() === false, '->getDefault() returns false if the option does not take a parameter');
  }

  public function testSetDefault()
  {
    $option = new InputOption('foo', null, InputOption::PARAMETER_REQUIRED, '', 'default');
    $option->setDefault(null);
    $this->assertTrue(is_null($option->getDefault()), '->setDefault() can reset the default value by passing null');
    $option->setDefault('another');
    $this->assertEquals($option->getDefault(), 'another', '->setDefault() changes the default value');

    $option = new InputOption('foo', null, InputOption::PARAMETER_REQUIRED | InputOption::PARAMETER_IS_ARRAY);
    $option->setDefault(array(1, 2));
    $this->assertEquals($option->getDefault(), array(1, 2), '->setDefault() changes the default value');

    $option = new InputOption('foo', 'f', InputOption::PARAMETER_NONE);
    try
    {
      $option->setDefault('default');
      $this->fail('->setDefault() throws an Exception if you give a default value for a PARAMETER_NONE option');
    }
    catch (\Exception $e)
    {
    }

    $option = new InputOption('foo', 'f', InputOption::PARAMETER_OPTIONAL | InputOption::PARAMETER_IS_ARRAY);
    try
    {
      $option->setDefault('default');
      $this->fail('->setDefault() throws an Exception if you give a default value which is not an array for a PARAMETER_IS_ARRAY option');
    }
    catch (\Exception $e)
    {
    }
  }
}
