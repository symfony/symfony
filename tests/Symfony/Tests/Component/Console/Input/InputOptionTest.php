<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Console\Input;

use Symfony\Component\Console\Input\InputOption;

class InputOptionTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $option = new InputOption('foo');
        $this->assertEquals('foo', $option->getName(), '__construct() takes a name as its first argument');
        $option = new InputOption('--foo');
        $this->assertEquals('foo', $option->getName(), '__construct() removes the leading -- of the option name');

        try {
            $option = new InputOption('foo', 'f', InputOption::VALUE_IS_ARRAY);
            $this->fail('->setDefault() throws an Exception if VALUE_IS_ARRAY option is used when an option does not accept a value');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\Exception', $e, '->setDefault() throws an Exception if VALUE_IS_ARRAY option is used when an option does not accept a value');
            $this->assertEquals('Impossible to have an option mode VALUE_IS_ARRAY if the option does not accept a value.', $e->getMessage());
        }

        // shortcut argument
        $option = new InputOption('foo', 'f');
        $this->assertEquals('f', $option->getShortcut(), '__construct() can take a shortcut as its second argument');
        $option = new InputOption('foo', '-f');
        $this->assertEquals('f', $option->getShortcut(), '__construct() removes the leading - of the shortcut');

        // mode argument
        $option = new InputOption('foo', 'f');
        $this->assertFalse($option->acceptValue(), '__construct() gives a "InputOption::VALUE_NONE" mode by default');
        $this->assertFalse($option->isValueRequired(), '__construct() gives a "InputOption::VALUE_NONE" mode by default');
        $this->assertFalse($option->isValueOptional(), '__construct() gives a "InputOption::VALUE_NONE" mode by default');

        $option = new InputOption('foo', 'f', null);
        $this->assertFalse($option->acceptValue(), '__construct() can take "InputOption::VALUE_NONE" as its mode');
        $this->assertFalse($option->isValueRequired(), '__construct() can take "InputOption::VALUE_NONE" as its mode');
        $this->assertFalse($option->isValueOptional(), '__construct() can take "InputOption::VALUE_NONE" as its mode');

        $option = new InputOption('foo', 'f', InputOption::VALUE_NONE);
        $this->assertFalse($option->acceptValue(), '__construct() can take "InputOption::VALUE_NONE" as its mode');
        $this->assertFalse($option->isValueRequired(), '__construct() can take "InputOption::VALUE_NONE" as its mode');
        $this->assertFalse($option->isValueOptional(), '__construct() can take "InputOption::VALUE_NONE" as its mode');

        $option = new InputOption('foo', 'f', InputOption::VALUE_REQUIRED);
        $this->assertTrue($option->acceptValue(), '__construct() can take "InputOption::VALUE_REQUIRED" as its mode');
        $this->assertTrue($option->isValueRequired(), '__construct() can take "InputOption::VALUE_REQUIRED" as its mode');
        $this->assertFalse($option->isValueOptional(), '__construct() can take "InputOption::VALUE_REQUIRED" as its mode');

        $option = new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL);
        $this->assertTrue($option->acceptValue(), '__construct() can take "InputOption::VALUE_OPTIONAL" as its mode');
        $this->assertFalse($option->isValueRequired(), '__construct() can take "InputOption::VALUE_OPTIONAL" as its mode');
        $this->assertTrue($option->isValueOptional(), '__construct() can take "InputOption::VALUE_OPTIONAL" as its mode');

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
        $option = new InputOption('foo', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY);
        $this->assertTrue($option->isArray(), '->isArray() returns true if the option can be an array');
        $option = new InputOption('foo', null, InputOption::VALUE_NONE);
        $this->assertFalse($option->isArray(), '->isArray() returns false if the option can not be an array');
    }

    public function testGetDescription()
    {
        $option = new InputOption('foo', 'f', null, 'Some description');
        $this->assertEquals('Some description', $option->getDescription(), '->getDescription() returns the description message');
    }

    public function testGetDefault()
    {
        $option = new InputOption('foo', null, InputOption::VALUE_OPTIONAL, '', 'default');
        $this->assertEquals('default', $option->getDefault(), '->getDefault() returns the default value');

        $option = new InputOption('foo', null, InputOption::VALUE_REQUIRED, '', 'default');
        $this->assertEquals('default', $option->getDefault(), '->getDefault() returns the default value');

        $option = new InputOption('foo', null, InputOption::VALUE_REQUIRED);
        $this->assertNull($option->getDefault(), '->getDefault() returns null if no default value is configured');

        $option = new InputOption('foo', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY);
        $this->assertEquals(array(), $option->getDefault(), '->getDefault() returns an empty array if option is an array');

        $option = new InputOption('foo', null, InputOption::VALUE_NONE);
        $this->assertFalse($option->getDefault(), '->getDefault() returns false if the option does not take a value');
    }

    public function testSetDefault()
    {
        $option = new InputOption('foo', null, InputOption::VALUE_REQUIRED, '', 'default');
        $option->setDefault(null);
        $this->assertNull($option->getDefault(), '->setDefault() can reset the default value by passing null');
        $option->setDefault('another');
        $this->assertEquals('another', $option->getDefault(), '->setDefault() changes the default value');

        $option = new InputOption('foo', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY);
        $option->setDefault(array(1, 2));
        $this->assertEquals(array(1, 2), $option->getDefault(), '->setDefault() changes the default value');

        $option = new InputOption('foo', 'f', InputOption::VALUE_NONE);
        try {
            $option->setDefault('default');
            $this->fail('->setDefault() throws an Exception if you give a default value for a VALUE_NONE option');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\Exception', $e, '->setDefault() throws an Exception if you give a default value for a VALUE_NONE option');
            $this->assertEquals('Cannot set a default value when using Option::VALUE_NONE mode.', $e->getMessage());
        }

        $option = new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY);
        try {
            $option->setDefault('default');
            $this->fail('->setDefault() throws an Exception if you give a default value which is not an array for a VALUE_IS_ARRAY option');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\Exception', $e, '->setDefault() throws an Exception if you give a default value which is not an array for a VALUE_IS_ARRAY option');
            $this->assertEquals('A default value for an array option must be an array.', $e->getMessage());
        }
    }
}
