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
use Symfony\Component\Console\Input\InputArgument;

class InputArgumentTest extends TestCase
{
    public function testConstructor()
    {
        $argument = new InputArgument('foo');
        $this->assertEquals('foo', $argument->getName(), '__construct() takes a name as its first argument');
    }

    public function testModes()
    {
        $argument = new InputArgument('foo');
        $this->assertFalse($argument->isRequired(), '__construct() gives a "InputArgument::OPTIONAL" mode by default');

        $argument = new InputArgument('foo', null);
        $this->assertFalse($argument->isRequired(), '__construct() can take "InputArgument::OPTIONAL" as its mode');

        $argument = new InputArgument('foo', InputArgument::OPTIONAL);
        $this->assertFalse($argument->isRequired(), '__construct() can take "InputArgument::OPTIONAL" as its mode');

        $argument = new InputArgument('foo', InputArgument::REQUIRED);
        $this->assertTrue($argument->isRequired(), '__construct() can take "InputArgument::REQUIRED" as its mode');
    }

    public function testInvalidModes()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument mode "-1" is not valid.');

        new InputArgument('foo', '-1');
    }

    public function testIsArray()
    {
        $argument = new InputArgument('foo', InputArgument::IS_ARRAY);
        $this->assertTrue($argument->isArray(), '->isArray() returns true if the argument can be an array');
        $argument = new InputArgument('foo', InputArgument::OPTIONAL | InputArgument::IS_ARRAY);
        $this->assertTrue($argument->isArray(), '->isArray() returns true if the argument can be an array');
        $argument = new InputArgument('foo', InputArgument::OPTIONAL);
        $this->assertFalse($argument->isArray(), '->isArray() returns false if the argument cannot be an array');
    }

    public function testGetDescription()
    {
        $argument = new InputArgument('foo', null, 'Some description');
        $this->assertEquals('Some description', $argument->getDescription(), '->getDescription() return the message description');
    }

    public function testGetDefault()
    {
        $argument = new InputArgument('foo', InputArgument::OPTIONAL, '', 'default');
        $this->assertEquals('default', $argument->getDefault(), '->getDefault() return the default value');
    }

    public function testSetDefault()
    {
        $argument = new InputArgument('foo', InputArgument::OPTIONAL, '', 'default');
        $argument->setDefault(null);
        $this->assertNull($argument->getDefault(), '->setDefault() can reset the default value by passing null');
        $argument->setDefault('another');
        $this->assertEquals('another', $argument->getDefault(), '->setDefault() changes the default value');

        $argument = new InputArgument('foo', InputArgument::OPTIONAL | InputArgument::IS_ARRAY);
        $argument->setDefault([1, 2]);
        $this->assertEquals([1, 2], $argument->getDefault(), '->setDefault() changes the default value');
    }

    public function testSetDefaultWithRequiredArgument()
    {
        $argument = new InputArgument('foo', InputArgument::REQUIRED);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot set a default value except for InputArgument::OPTIONAL mode.');

        $argument->setDefault('default');
    }

    public function testSetDefaultWithRequiredArrayArgument()
    {
        $argument = new InputArgument('foo', InputArgument::REQUIRED | InputArgument::IS_ARRAY);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot set a default value except for InputArgument::OPTIONAL mode.');

        $argument->setDefault([]);
    }

    public function testSetDefaultWithArrayArgument()
    {
        $argument = new InputArgument('foo', InputArgument::IS_ARRAY);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A default value for an array argument must be an array.');

        $argument->setDefault('default');
    }

    public function testCompleteArray()
    {
        $values = ['foo', 'bar'];
        $argument = new InputArgument('foo', null, '', null, $values);
        $this->assertTrue($argument->hasCompletion());
        $suggestions = new CompletionSuggestions();
        $argument->complete(new CompletionInput(), $suggestions);
        $this->assertSame($values, array_map(fn (Suggestion $suggestion) => $suggestion->getValue(), $suggestions->getValueSuggestions()));
    }

    public function testCompleteClosure()
    {
        $values = ['foo', 'bar'];
        $argument = new InputArgument('foo', null, '', null, fn (CompletionInput $input): array => $values);
        $this->assertTrue($argument->hasCompletion());
        $suggestions = new CompletionSuggestions();
        $argument->complete(new CompletionInput(), $suggestions);
        $this->assertSame($values, array_map(fn (Suggestion $suggestion) => $suggestion->getValue(), $suggestions->getValueSuggestions()));
    }

    public function testCompleteClosureReturnIncorrectType()
    {
        $argument = new InputArgument('foo', InputArgument::OPTIONAL, '', null, fn (CompletionInput $input) => 'invalid');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Closure for argument "foo" must return an array. Got "string".');

        $argument->complete(new CompletionInput(), new CompletionSuggestions());
    }
}
