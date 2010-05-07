<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Console\Input;

use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Exception;

class InputOptionTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $option = new InputOption('foo');
        $this->assertEquals('foo', $option->getName(), '__construct() takes a name as its first argument');
        $option = new InputOption('--foo');
        $this->assertEquals('foo', $option->getName(), '__construct() removes the leading -- of the option name');

        try {
            $option = new InputOption('foo', 'f', InputOption::PARAMETER_IS_ARRAY);
            $this->fail('->setDefault() throws an Exception if PARAMETER_IS_ARRAY option is used when an option does not accept a value');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\Exception', $e, '->setDefault() throws an Exception if PARAMETER_IS_ARRAY option is used when an option does not accept a value');
            $this->assertEquals('Impossible to have an option mode PARAMETER_IS_ARRAY if the option does not accept a parameter.', $e->getMessage());
        }

        // shortcut argument
        $option = new InputOption('foo', 'f');
        $this->assertEquals('f', $option->getShortcut(), '__construct() can take a shortcut as its second argument');
        $option = new InputOption('foo', '-f');
        $this->assertEquals('f', $option->getShortcut(), '__construct() removes the leading - of the shortcut');

        // mode argument
        $option = new InputOption('foo', 'f');
        $this->assertFalse($option->acceptParameter(), '__construct() gives a "Option::PARAMETER_NONE" mode by default');
        $this->assertFalse($option->isParameterRequired(), '__construct() gives a "Option::PARAMETER_NONE" mode by default');
        $this->assertFalse($option->isParameterOptional(), '__construct() gives a "Option::PARAMETER_NONE" mode by default');

        $option = new InputOption('foo', 'f', null);
        $this->assertFalse($option->acceptParameter(), '__construct() can take "Option::PARAMETER_NONE" as its mode');
        $this->assertFalse($option->isParameterRequired(), '__construct() can take "Option::PARAMETER_NONE" as its mode');
        $this->assertFalse($option->isParameterOptional(), '__construct() can take "Option::PARAMETER_NONE" as its mode');

        $option = new InputOption('foo', 'f', InputOption::PARAMETER_NONE);
        $this->assertFalse($option->acceptParameter(), '__construct() can take "Option::PARAMETER_NONE" as its mode');
        $this->assertFalse($option->isParameterRequired(), '__construct() can take "Option::PARAMETER_NONE" as its mode');
        $this->assertFalse($option->isParameterOptional(), '__construct() can take "Option::PARAMETER_NONE" as its mode');

        $option = new InputOption('foo', 'f', InputOption::PARAMETER_REQUIRED);
        $this->assertTrue($option->acceptParameter(), '__construct() can take "Option::PARAMETER_REQUIRED" as its mode');
        $this->assertTrue($option->isParameterRequired(), '__construct() can take "Option::PARAMETER_REQUIRED" as its mode');
        $this->assertFalse($option->isParameterOptional(), '__construct() can take "Option::PARAMETER_REQUIRED" as its mode');

        $option = new InputOption('foo', 'f', InputOption::PARAMETER_OPTIONAL);
        $this->assertTrue($option->acceptParameter(), '__construct() can take "Option::PARAMETER_OPTIONAL" as its mode');
        $this->assertFalse($option->isParameterRequired(), '__construct() can take "Option::PARAMETER_OPTIONAL" as its mode');
        $this->assertTrue($option->isParameterOptional(), '__construct() can take "Option::PARAMETER_OPTIONAL" as its mode');

        try {
            $option = new InputOption('foo', 'f', 'ANOTHER_ONE');
            $this->fail('__construct() throws an Exception if the mode is not valid');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\Exception', $e, '__construct() throws an Exception if the mode is not valid');
            $this->assertEquals('Option mode "ANOTHER_ONE" is not valid.', $e->getMessage());
        }
    }

    public function testIsArray()
    {
        $option = new InputOption('foo', null, InputOption::PARAMETER_OPTIONAL | InputOption::PARAMETER_IS_ARRAY);
        $this->assertTrue($option->isArray(), '->isArray() returns true if the option can be an array');
        $option = new InputOption('foo', null, InputOption::PARAMETER_NONE);
        $this->assertFalse($option->isArray(), '->isArray() returns false if the option can not be an array');
    }

    public function testGetDescription()
    {
        $option = new InputOption('foo', 'f', null, 'Some description');
        $this->assertEquals('Some description', $option->getDescription(), '->getDescription() returns the description message');
    }

    public function testGetDefault()
    {
        $option = new InputOption('foo', null, InputOption::PARAMETER_OPTIONAL, '', 'default');
        $this->assertEquals('default', $option->getDefault(), '->getDefault() returns the default value');

        $option = new InputOption('foo', null, InputOption::PARAMETER_REQUIRED, '', 'default');
        $this->assertEquals('default', $option->getDefault(), '->getDefault() returns the default value');

        $option = new InputOption('foo', null, InputOption::PARAMETER_REQUIRED);
        $this->assertNull($option->getDefault(), '->getDefault() returns null if no default value is configured');

        $option = new InputOption('foo', null, InputOption::PARAMETER_OPTIONAL | InputOption::PARAMETER_IS_ARRAY);
        $this->assertEquals(array(), $option->getDefault(), '->getDefault() returns an empty array if option is an array');

        $option = new InputOption('foo', null, InputOption::PARAMETER_NONE);
        $this->assertFalse($option->getDefault(), '->getDefault() returns false if the option does not take a parameter');
    }

    public function testSetDefault()
    {
        $option = new InputOption('foo', null, InputOption::PARAMETER_REQUIRED, '', 'default');
        $option->setDefault(null);
        $this->assertNull($option->getDefault(), '->setDefault() can reset the default value by passing null');
        $option->setDefault('another');
        $this->assertEquals('another', $option->getDefault(), '->setDefault() changes the default value');

        $option = new InputOption('foo', null, InputOption::PARAMETER_REQUIRED | InputOption::PARAMETER_IS_ARRAY);
        $option->setDefault(array(1, 2));
        $this->assertEquals(array(1, 2), $option->getDefault(), '->setDefault() changes the default value');

        $option = new InputOption('foo', 'f', InputOption::PARAMETER_NONE);
        try {
            $option->setDefault('default');
            $this->fail('->setDefault() throws an Exception if you give a default value for a PARAMETER_NONE option');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\Exception', $e, '->setDefault() throws an Exception if you give a default value for a PARAMETER_NONE option');
            $this->assertEquals('Cannot set a default value when using Option::PARAMETER_NONE mode.', $e->getMessage());
        }

        $option = new InputOption('foo', 'f', InputOption::PARAMETER_OPTIONAL | InputOption::PARAMETER_IS_ARRAY);
        try {
            $option->setDefault('default');
            $this->fail('->setDefault() throws an Exception if you give a default value which is not an array for a PARAMETER_IS_ARRAY option');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\Exception', $e, '->setDefault() throws an Exception if you give a default value which is not an array for a PARAMETER_IS_ARRAY option');
            $this->assertEquals('A default value for an array option must be an array.', $e->getMessage());
        }
    }
}
