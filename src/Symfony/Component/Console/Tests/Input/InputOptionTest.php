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
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputOption;

class InputOptionTest extends TestCase
{
    public function testConstructor()
    {
        $option = new InputOption('foo');
        $this->assertSame('foo', $option->getName(), '__construct() takes a name as its first argument');
        $option = new InputOption('--foo');
        $this->assertSame('foo', $option->getName(), '__construct() removes the leading -- of the option name');
    }

    public function testArrayModeWithoutValue()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Impossible to have an option mode VALUE_IS_ARRAY if the option does not accept a value.');
        new InputOption('foo', 'f', InputOption::VALUE_IS_ARRAY);
    }

    public function testBooleanWithRequired()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Impossible to have an option mode VALUE_NEGATABLE if the option also accepts a value.');
        new InputOption('foo', 'f', InputOption::VALUE_REQUIRED | InputOption::VALUE_NEGATABLE);
    }

    public function testBooleanWithOptional()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Impossible to have an option mode VALUE_NEGATABLE if the option also accepts a value.');
        new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL | InputOption::VALUE_NEGATABLE);
    }

    public function testShortcut()
    {
        $option = new InputOption('foo', 'f');
        $this->assertSame('f', $option->getShortcut(), '__construct() can take a shortcut as its second argument');
        $option = new InputOption('foo', '-f|-ff|fff');
        $this->assertSame('f|ff|fff', $option->getShortcut(), '__construct() removes the leading - of the shortcuts');
        $option = new InputOption('foo', ['f', 'ff', '-fff']);
        $this->assertSame('f|ff|fff', $option->getShortcut(), '__construct() removes the leading - of the shortcuts');
        $option = new InputOption('foo');
        $this->assertNull($option->getShortcut(), '__construct() makes the shortcut null by default');
        $option = new InputOption('foo', '');
        $this->assertNull($option->getShortcut(), '__construct() makes the shortcut null when given an empty string');
        $option = new InputOption('foo', []);
        $this->assertNull($option->getShortcut(), '__construct() makes the shortcut null when given an empty array');
        $option = new InputOption('foo', ['f', '', 'fff']);
        $this->assertSame('f|fff', $option->getShortcut(), '__construct() removes empty shortcuts');
        $option = new InputOption('foo', 'f||fff');
        $this->assertSame('f|fff', $option->getShortcut(), '__construct() removes empty shortcuts');
        $option = new InputOption('foo', '0');
        $this->assertSame('0', $option->getShortcut(), '-0 is an acceptable shortcut value');
        $option = new InputOption('foo', ['0', 'z']);
        $this->assertSame('0|z', $option->getShortcut(), '-0 is an acceptable shortcut value when embedded in an array');
        $option = new InputOption('foo', '0|z');
        $this->assertSame('0|z', $option->getShortcut(), '-0 is an acceptable shortcut value when embedded in a string-list');
        $option = new InputOption('foo', false);
        $this->assertNull($option->getShortcut(), '__construct() makes the shortcut null when given a false as value');
    }

    public function testModes()
    {
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
    }

    public function testInvalidModes()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Option mode "-1" is not valid.');

        new InputOption('foo', 'f', '-1');
    }

    public function testEmptyNameIsInvalid()
    {
        $this->expectException(\InvalidArgumentException::class);
        new InputOption('');
    }

    public function testDoubleDashNameIsInvalid()
    {
        $this->expectException(\InvalidArgumentException::class);
        new InputOption('--');
    }

    public function testSingleDashOptionIsInvalid()
    {
        $this->expectException(\InvalidArgumentException::class);
        new InputOption('foo', '-');
    }

    public function testIsArray()
    {
        $option = new InputOption('foo', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY);
        $this->assertTrue($option->isArray(), '->isArray() returns true if the option can be an array');
        $option = new InputOption('foo', null, InputOption::VALUE_NONE);
        $this->assertFalse($option->isArray(), '->isArray() returns false if the option cannot be an array');
    }

    public function testGetDescription()
    {
        $option = new InputOption('foo', 'f', null, 'Some description');
        $this->assertSame('Some description', $option->getDescription(), '->getDescription() returns the description message');
    }

    public function testGetDefault()
    {
        $option = new InputOption('foo', null, InputOption::VALUE_OPTIONAL, '', 'default');
        $this->assertSame('default', $option->getDefault(), '->getDefault() returns the default value');

        $option = new InputOption('foo', null, InputOption::VALUE_REQUIRED, '', 'default');
        $this->assertSame('default', $option->getDefault(), '->getDefault() returns the default value');

        $option = new InputOption('foo', null, InputOption::VALUE_REQUIRED);
        $this->assertNull($option->getDefault(), '->getDefault() returns null if no default value is configured');

        $option = new InputOption('foo', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY);
        $this->assertSame([], $option->getDefault(), '->getDefault() returns an empty array if option is an array');

        $option = new InputOption('foo', null, InputOption::VALUE_NONE);
        $this->assertFalse($option->getDefault(), '->getDefault() returns false if the option does not take a value');
    }

    public function testSetDefault()
    {
        $option = new InputOption('foo', null, InputOption::VALUE_REQUIRED, '', 'default');
        $option->setDefault(null);
        $this->assertNull($option->getDefault(), '->setDefault() can reset the default value by passing null');
        $option->setDefault('another');
        $this->assertSame('another', $option->getDefault(), '->setDefault() changes the default value');

        $option = new InputOption('foo', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY);
        $option->setDefault([1, 2]);
        $this->assertSame([1, 2], $option->getDefault(), '->setDefault() changes the default value');
    }

    public function testDefaultValueWithValueNoneMode()
    {
        $option = new InputOption('foo', 'f', InputOption::VALUE_NONE);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot set a default value when using InputOption::VALUE_NONE mode.');

        $option->setDefault('default');
    }

    public function testDefaultValueWithIsArrayMode()
    {
        $option = new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A default value for an array option must be an array.');

        $option->setDefault('default');
    }

    public function testEquals()
    {
        $option = new InputOption('foo', 'f', null, 'Some description');
        $option2 = new InputOption('foo', 'f', null, 'Alternative description');
        $this->assertTrue($option->equals($option2));

        $option = new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL, 'Some description');
        $option2 = new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL, 'Some description', true);
        $this->assertFalse($option->equals($option2));

        $option = new InputOption('foo', 'f', null, 'Some description');
        $option2 = new InputOption('bar', 'f', null, 'Some description');
        $this->assertFalse($option->equals($option2));

        $option = new InputOption('foo', 'f', null, 'Some description');
        $option2 = new InputOption('foo', '', null, 'Some description');
        $this->assertFalse($option->equals($option2));

        $option = new InputOption('foo', 'f', null, 'Some description');
        $option2 = new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL, 'Some description');
        $this->assertFalse($option->equals($option2));
    }

    public function testSuggestedValuesErrorIfNoValue()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot set suggested values if the option does not accept a value.');

        new InputOption('foo', null, InputOption::VALUE_NONE, '', null, ['foo']);
    }

    public function testCompleteArray()
    {
        $values = ['foo', 'bar'];
        $option = new InputOption('foo', null, InputOption::VALUE_OPTIONAL, '', null, $values);
        $this->assertTrue($option->hasCompletion());
        $suggestions = new CompletionSuggestions();
        $option->complete(new CompletionInput(), $suggestions);
        $this->assertSame($values, array_map(fn (Suggestion $suggestion) => $suggestion->getValue(), $suggestions->getValueSuggestions()));
    }

    public function testCompleteClosure()
    {
        $values = ['foo', 'bar'];
        $option = new InputOption('foo', null, InputOption::VALUE_OPTIONAL, '', null, fn (CompletionInput $input): array => $values);
        $this->assertTrue($option->hasCompletion());
        $suggestions = new CompletionSuggestions();
        $option->complete(new CompletionInput(), $suggestions);
        $this->assertSame($values, array_map(fn (Suggestion $suggestion) => $suggestion->getValue(), $suggestions->getValueSuggestions()));
    }

    public function testCompleteClosureReturnIncorrectType()
    {
        $option = new InputOption('foo', null, InputOption::VALUE_OPTIONAL, '', null, fn (CompletionInput $input) => 'invalid');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Closure for option "foo" must return an array. Got "string".');

        $option->complete(new CompletionInput(), new CompletionSuggestions());
    }
}
