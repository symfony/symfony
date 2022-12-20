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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputOption;

class InputOptionTest extends TestCase
{
    public function testConstructor()
    {
        $option = new InputOption('foo');
        self::assertEquals('foo', $option->getName(), '__construct() takes a name as its first argument');
        $option = new InputOption('--foo');
        self::assertEquals('foo', $option->getName(), '__construct() removes the leading -- of the option name');
    }

    public function testArrayModeWithoutValue()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Impossible to have an option mode VALUE_IS_ARRAY if the option does not accept a value.');
        new InputOption('foo', 'f', InputOption::VALUE_IS_ARRAY);
    }

    public function testBooleanWithRequired()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Impossible to have an option mode VALUE_NEGATABLE if the option also accepts a value.');
        new InputOption('foo', 'f', InputOption::VALUE_REQUIRED | InputOption::VALUE_NEGATABLE);
    }

    public function testBooleanWithOptional()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Impossible to have an option mode VALUE_NEGATABLE if the option also accepts a value.');
        new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL | InputOption::VALUE_NEGATABLE);
    }

    public function testShortcut()
    {
        $option = new InputOption('foo', 'f');
        self::assertEquals('f', $option->getShortcut(), '__construct() can take a shortcut as its second argument');
        $option = new InputOption('foo', '-f|-ff|fff');
        self::assertEquals('f|ff|fff', $option->getShortcut(), '__construct() removes the leading - of the shortcuts');
        $option = new InputOption('foo', ['f', 'ff', '-fff']);
        self::assertEquals('f|ff|fff', $option->getShortcut(), '__construct() removes the leading - of the shortcuts');
        $option = new InputOption('foo');
        self::assertNull($option->getShortcut(), '__construct() makes the shortcut null by default');
    }

    public function testModes()
    {
        $option = new InputOption('foo', 'f');
        self::assertFalse($option->acceptValue(), '__construct() gives a "InputOption::VALUE_NONE" mode by default');
        self::assertFalse($option->isValueRequired(), '__construct() gives a "InputOption::VALUE_NONE" mode by default');
        self::assertFalse($option->isValueOptional(), '__construct() gives a "InputOption::VALUE_NONE" mode by default');

        $option = new InputOption('foo', 'f', null);
        self::assertFalse($option->acceptValue(), '__construct() can take "InputOption::VALUE_NONE" as its mode');
        self::assertFalse($option->isValueRequired(), '__construct() can take "InputOption::VALUE_NONE" as its mode');
        self::assertFalse($option->isValueOptional(), '__construct() can take "InputOption::VALUE_NONE" as its mode');

        $option = new InputOption('foo', 'f', InputOption::VALUE_NONE);
        self::assertFalse($option->acceptValue(), '__construct() can take "InputOption::VALUE_NONE" as its mode');
        self::assertFalse($option->isValueRequired(), '__construct() can take "InputOption::VALUE_NONE" as its mode');
        self::assertFalse($option->isValueOptional(), '__construct() can take "InputOption::VALUE_NONE" as its mode');

        $option = new InputOption('foo', 'f', InputOption::VALUE_REQUIRED);
        self::assertTrue($option->acceptValue(), '__construct() can take "InputOption::VALUE_REQUIRED" as its mode');
        self::assertTrue($option->isValueRequired(), '__construct() can take "InputOption::VALUE_REQUIRED" as its mode');
        self::assertFalse($option->isValueOptional(), '__construct() can take "InputOption::VALUE_REQUIRED" as its mode');

        $option = new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL);
        self::assertTrue($option->acceptValue(), '__construct() can take "InputOption::VALUE_OPTIONAL" as its mode');
        self::assertFalse($option->isValueRequired(), '__construct() can take "InputOption::VALUE_OPTIONAL" as its mode');
        self::assertTrue($option->isValueOptional(), '__construct() can take "InputOption::VALUE_OPTIONAL" as its mode');
    }

    public function testInvalidModes()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Option mode "-1" is not valid.');

        new InputOption('foo', 'f', '-1');
    }

    public function testEmptyNameIsInvalid()
    {
        self::expectException(\InvalidArgumentException::class);
        new InputOption('');
    }

    public function testDoubleDashNameIsInvalid()
    {
        self::expectException(\InvalidArgumentException::class);
        new InputOption('--');
    }

    public function testSingleDashOptionIsInvalid()
    {
        self::expectException(\InvalidArgumentException::class);
        new InputOption('foo', '-');
    }

    public function testIsArray()
    {
        $option = new InputOption('foo', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY);
        self::assertTrue($option->isArray(), '->isArray() returns true if the option can be an array');
        $option = new InputOption('foo', null, InputOption::VALUE_NONE);
        self::assertFalse($option->isArray(), '->isArray() returns false if the option cannot be an array');
    }

    public function testGetDescription()
    {
        $option = new InputOption('foo', 'f', null, 'Some description');
        self::assertEquals('Some description', $option->getDescription(), '->getDescription() returns the description message');
    }

    public function testGetDefault()
    {
        $option = new InputOption('foo', null, InputOption::VALUE_OPTIONAL, '', 'default');
        self::assertEquals('default', $option->getDefault(), '->getDefault() returns the default value');

        $option = new InputOption('foo', null, InputOption::VALUE_REQUIRED, '', 'default');
        self::assertEquals('default', $option->getDefault(), '->getDefault() returns the default value');

        $option = new InputOption('foo', null, InputOption::VALUE_REQUIRED);
        self::assertNull($option->getDefault(), '->getDefault() returns null if no default value is configured');

        $option = new InputOption('foo', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY);
        self::assertEquals([], $option->getDefault(), '->getDefault() returns an empty array if option is an array');

        $option = new InputOption('foo', null, InputOption::VALUE_NONE);
        self::assertFalse($option->getDefault(), '->getDefault() returns false if the option does not take a value');
    }

    public function testSetDefault()
    {
        $option = new InputOption('foo', null, InputOption::VALUE_REQUIRED, '', 'default');
        $option->setDefault(null);
        self::assertNull($option->getDefault(), '->setDefault() can reset the default value by passing null');
        $option->setDefault('another');
        self::assertEquals('another', $option->getDefault(), '->setDefault() changes the default value');

        $option = new InputOption('foo', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY);
        $option->setDefault([1, 2]);
        self::assertEquals([1, 2], $option->getDefault(), '->setDefault() changes the default value');
    }

    public function testDefaultValueWithValueNoneMode()
    {
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Cannot set a default value when using InputOption::VALUE_NONE mode.');
        $option = new InputOption('foo', 'f', InputOption::VALUE_NONE);
        $option->setDefault('default');
    }

    public function testDefaultValueWithIsArrayMode()
    {
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('A default value for an array option must be an array.');
        $option = new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY);
        $option->setDefault('default');
    }

    public function testEquals()
    {
        $option = new InputOption('foo', 'f', null, 'Some description');
        $option2 = new InputOption('foo', 'f', null, 'Alternative description');
        self::assertTrue($option->equals($option2));

        $option = new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL, 'Some description');
        $option2 = new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL, 'Some description', true);
        self::assertFalse($option->equals($option2));

        $option = new InputOption('foo', 'f', null, 'Some description');
        $option2 = new InputOption('bar', 'f', null, 'Some description');
        self::assertFalse($option->equals($option2));

        $option = new InputOption('foo', 'f', null, 'Some description');
        $option2 = new InputOption('foo', '', null, 'Some description');
        self::assertFalse($option->equals($option2));

        $option = new InputOption('foo', 'f', null, 'Some description');
        $option2 = new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL, 'Some description');
        self::assertFalse($option->equals($option2));
    }
}
