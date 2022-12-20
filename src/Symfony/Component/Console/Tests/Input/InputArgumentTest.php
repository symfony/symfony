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
use Symfony\Component\Console\Input\InputArgument;

class InputArgumentTest extends TestCase
{
    public function testConstructor()
    {
        $argument = new InputArgument('foo');
        self::assertEquals('foo', $argument->getName(), '__construct() takes a name as its first argument');
    }

    public function testModes()
    {
        $argument = new InputArgument('foo');
        self::assertFalse($argument->isRequired(), '__construct() gives a "InputArgument::OPTIONAL" mode by default');

        $argument = new InputArgument('foo', null);
        self::assertFalse($argument->isRequired(), '__construct() can take "InputArgument::OPTIONAL" as its mode');

        $argument = new InputArgument('foo', InputArgument::OPTIONAL);
        self::assertFalse($argument->isRequired(), '__construct() can take "InputArgument::OPTIONAL" as its mode');

        $argument = new InputArgument('foo', InputArgument::REQUIRED);
        self::assertTrue($argument->isRequired(), '__construct() can take "InputArgument::REQUIRED" as its mode');
    }

    public function testInvalidModes()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Argument mode "-1" is not valid.');

        new InputArgument('foo', '-1');
    }

    public function testIsArray()
    {
        $argument = new InputArgument('foo', InputArgument::IS_ARRAY);
        self::assertTrue($argument->isArray(), '->isArray() returns true if the argument can be an array');
        $argument = new InputArgument('foo', InputArgument::OPTIONAL | InputArgument::IS_ARRAY);
        self::assertTrue($argument->isArray(), '->isArray() returns true if the argument can be an array');
        $argument = new InputArgument('foo', InputArgument::OPTIONAL);
        self::assertFalse($argument->isArray(), '->isArray() returns false if the argument cannot be an array');
    }

    public function testGetDescription()
    {
        $argument = new InputArgument('foo', null, 'Some description');
        self::assertEquals('Some description', $argument->getDescription(), '->getDescription() return the message description');
    }

    public function testGetDefault()
    {
        $argument = new InputArgument('foo', InputArgument::OPTIONAL, '', 'default');
        self::assertEquals('default', $argument->getDefault(), '->getDefault() return the default value');
    }

    public function testSetDefault()
    {
        $argument = new InputArgument('foo', InputArgument::OPTIONAL, '', 'default');
        $argument->setDefault(null);
        self::assertNull($argument->getDefault(), '->setDefault() can reset the default value by passing null');
        $argument->setDefault('another');
        self::assertEquals('another', $argument->getDefault(), '->setDefault() changes the default value');

        $argument = new InputArgument('foo', InputArgument::OPTIONAL | InputArgument::IS_ARRAY);
        $argument->setDefault([1, 2]);
        self::assertEquals([1, 2], $argument->getDefault(), '->setDefault() changes the default value');
    }

    public function testSetDefaultWithRequiredArgument()
    {
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Cannot set a default value except for InputArgument::OPTIONAL mode.');
        $argument = new InputArgument('foo', InputArgument::REQUIRED);
        $argument->setDefault('default');
    }

    public function testSetDefaultWithRequiredArrayArgument()
    {
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Cannot set a default value except for InputArgument::OPTIONAL mode.');
        $argument = new InputArgument('foo', InputArgument::REQUIRED | InputArgument::IS_ARRAY);
        $argument->setDefault([]);
    }

    public function testSetDefaultWithArrayArgument()
    {
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('A default value for an array argument must be an array.');
        $argument = new InputArgument('foo', InputArgument::IS_ARRAY);
        $argument->setDefault('default');
    }
}
