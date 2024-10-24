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
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class InputDefinitionTest extends TestCase
{
    protected static string $fixtures;

    protected InputArgument|InputOption|null $multi;
    protected InputArgument|InputOption|null $foo;
    protected InputArgument|InputOption|null $bar;
    protected InputArgument|InputOption|null $foo1;
    protected InputArgument|InputOption|null $foo2;

    public static function setUpBeforeClass(): void
    {
        self::$fixtures = __DIR__.'/../Fixtures/';
    }

    public function testConstructorArguments()
    {
        $this->initializeArguments();

        $definition = new InputDefinition();
        $this->assertSame([], $definition->getArguments(), '__construct() creates a new InputDefinition object');

        $definition = new InputDefinition([$this->foo, $this->bar]);
        $this->assertSame(['foo' => $this->foo, 'bar' => $this->bar], $definition->getArguments(), '__construct() takes an array of InputArgument objects as its first argument');
    }

    public function testConstructorOptions()
    {
        $this->initializeOptions();

        $definition = new InputDefinition();
        $this->assertSame([], $definition->getOptions(), '__construct() creates a new InputDefinition object');

        $definition = new InputDefinition([$this->foo, $this->bar]);
        $this->assertSame(['foo' => $this->foo, 'bar' => $this->bar], $definition->getOptions(), '__construct() takes an array of InputOption objects as its first argument');
    }

    public function testSetArguments()
    {
        $this->initializeArguments();

        $definition = new InputDefinition();
        $definition->setArguments([$this->foo]);
        $this->assertSame(['foo' => $this->foo], $definition->getArguments(), '->setArguments() sets the array of InputArgument objects');
        $definition->setArguments([$this->bar]);

        $this->assertSame(['bar' => $this->bar], $definition->getArguments(), '->setArguments() clears all InputArgument objects');
    }

    public function testAddArguments()
    {
        $this->initializeArguments();

        $definition = new InputDefinition();
        $definition->addArguments([$this->foo]);
        $this->assertSame(['foo' => $this->foo], $definition->getArguments(), '->addArguments() adds an array of InputArgument objects');
        $definition->addArguments([$this->bar]);
        $this->assertSame(['foo' => $this->foo, 'bar' => $this->bar], $definition->getArguments(), '->addArguments() does not clear existing InputArgument objects');
    }

    public function testAddArgument()
    {
        $this->initializeArguments();

        $definition = new InputDefinition();
        $definition->addArgument($this->foo);
        $this->assertSame(['foo' => $this->foo], $definition->getArguments(), '->addArgument() adds a InputArgument object');
        $definition->addArgument($this->bar);
        $this->assertSame(['foo' => $this->foo, 'bar' => $this->bar], $definition->getArguments(), '->addArgument() adds a InputArgument object');
    }

    public function testArgumentsMustHaveDifferentNames()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('An argument with name "foo" already exists.');
        $this->initializeArguments();

        $definition = new InputDefinition();
        $definition->addArgument($this->foo);
        $definition->addArgument($this->foo1);
    }

    public function testArrayArgumentHasToBeLast()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot add a required argument "anotherbar" after an array argument "fooarray".');
        $this->initializeArguments();

        $definition = new InputDefinition();
        $definition->addArgument(new InputArgument('fooarray', InputArgument::IS_ARRAY));
        $definition->addArgument(new InputArgument('anotherbar'));
    }

    public function testRequiredArgumentCannotFollowAnOptionalOne()
    {
        $this->initializeArguments();
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('Cannot add a required argument "%s" after an optional one "%s".', $this->foo2->getName(), $this->foo->getName()));

        $definition = new InputDefinition();
        $definition->addArgument($this->foo);
        $definition->addArgument($this->foo2);
    }

    public function testGetArgument()
    {
        $this->initializeArguments();

        $definition = new InputDefinition();
        $definition->addArguments([$this->foo]);
        $this->assertSame($this->foo, $definition->getArgument('foo'), '->getArgument() returns a InputArgument by its name');
    }

    public function testGetInvalidArgument()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "bar" argument does not exist.');
        $this->initializeArguments();

        $definition = new InputDefinition();
        $definition->addArguments([$this->foo]);
        $definition->getArgument('bar');
    }

    public function testHasArgument()
    {
        $this->initializeArguments();

        $definition = new InputDefinition();
        $definition->addArguments([$this->foo]);

        $this->assertTrue($definition->hasArgument('foo'), '->hasArgument() returns true if a InputArgument exists for the given name');
        $this->assertFalse($definition->hasArgument('bar'), '->hasArgument() returns false if a InputArgument exists for the given name');
    }

    public function testGetArgumentRequiredCount()
    {
        $this->initializeArguments();

        $definition = new InputDefinition();
        $definition->addArgument($this->foo2);
        $this->assertSame(1, $definition->getArgumentRequiredCount(), '->getArgumentRequiredCount() returns the number of required arguments');
        $definition->addArgument($this->foo);
        $this->assertSame(1, $definition->getArgumentRequiredCount(), '->getArgumentRequiredCount() returns the number of required arguments');
    }

    public function testGetArgumentCount()
    {
        $this->initializeArguments();

        $definition = new InputDefinition();
        $definition->addArgument($this->foo2);
        $this->assertSame(1, $definition->getArgumentCount(), '->getArgumentCount() returns the number of arguments');
        $definition->addArgument($this->foo);
        $this->assertSame(2, $definition->getArgumentCount(), '->getArgumentCount() returns the number of arguments');
    }

    public function testGetArgumentDefaults()
    {
        $definition = new InputDefinition([
            new InputArgument('foo1', InputArgument::OPTIONAL),
            new InputArgument('foo2', InputArgument::OPTIONAL, '', 'default'),
            new InputArgument('foo3', InputArgument::OPTIONAL | InputArgument::IS_ARRAY),
        //  new InputArgument('foo4', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, '', [1, 2]),
        ]);
        $this->assertSame(['foo1' => null, 'foo2' => 'default', 'foo3' => []], $definition->getArgumentDefaults(), '->getArgumentDefaults() return the default values for each argument');

        $definition = new InputDefinition([
            new InputArgument('foo4', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, '', [1, 2]),
        ]);
        $this->assertSame(['foo4' => [1, 2]], $definition->getArgumentDefaults(), '->getArgumentDefaults() return the default values for each argument');
    }

    public function testSetOptions()
    {
        $this->initializeOptions();

        $definition = new InputDefinition([$this->foo]);
        $this->assertSame(['foo' => $this->foo], $definition->getOptions(), '->setOptions() sets the array of InputOption objects');
        $definition->setOptions([$this->bar]);
        $this->assertSame(['bar' => $this->bar], $definition->getOptions(), '->setOptions() clears all InputOption objects');
    }

    public function testSetOptionsClearsOptions()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "-f" option does not exist.');
        $this->initializeOptions();

        $definition = new InputDefinition([$this->foo]);
        $definition->setOptions([$this->bar]);
        $definition->getOptionForShortcut('f');
    }

    public function testAddOptions()
    {
        $this->initializeOptions();

        $definition = new InputDefinition([$this->foo]);
        $this->assertSame(['foo' => $this->foo], $definition->getOptions(), '->addOptions() adds an array of InputOption objects');
        $definition->addOptions([$this->bar]);
        $this->assertSame(['foo' => $this->foo, 'bar' => $this->bar], $definition->getOptions(), '->addOptions() does not clear existing InputOption objects');
    }

    public function testAddOption()
    {
        $this->initializeOptions();

        $definition = new InputDefinition();
        $definition->addOption($this->foo);
        $this->assertSame(['foo' => $this->foo], $definition->getOptions(), '->addOption() adds a InputOption object');
        $definition->addOption($this->bar);
        $this->assertSame(['foo' => $this->foo, 'bar' => $this->bar], $definition->getOptions(), '->addOption() adds a InputOption object');
    }

    public function testAddDuplicateOption()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('An option named "foo" already exists.');
        $this->initializeOptions();

        $definition = new InputDefinition();
        $definition->addOption($this->foo);
        $definition->addOption($this->foo2);
    }

    public function testAddDuplicateNegatedOption()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('An option named "no-foo" already exists.');

        $definition = new InputDefinition();
        $definition->addOption(new InputOption('no-foo'));
        $definition->addOption(new InputOption('foo', null, InputOption::VALUE_NEGATABLE));
    }

    public function testAddDuplicateNegatedReverseOption()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('An option named "no-foo" already exists.');

        $definition = new InputDefinition();
        $definition->addOption(new InputOption('foo', null, InputOption::VALUE_NEGATABLE));
        $definition->addOption(new InputOption('no-foo'));
    }

    public function testAddDuplicateShortcutOption()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('An option with shortcut "f" already exists.');
        $this->initializeOptions();

        $definition = new InputDefinition();
        $definition->addOption($this->foo);
        $definition->addOption($this->foo1);
    }

    public function testGetOption()
    {
        $this->initializeOptions();

        $definition = new InputDefinition([$this->foo]);
        $this->assertSame($this->foo, $definition->getOption('foo'), '->getOption() returns a InputOption by its name');
    }

    public function testGetInvalidOption()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "--bar" option does not exist.');
        $this->initializeOptions();

        $definition = new InputDefinition([$this->foo]);
        $definition->getOption('bar');
    }

    public function testHasOption()
    {
        $this->initializeOptions();

        $definition = new InputDefinition([$this->foo]);
        $this->assertTrue($definition->hasOption('foo'), '->hasOption() returns true if a InputOption exists for the given name');
        $this->assertFalse($definition->hasOption('bar'), '->hasOption() returns false if a InputOption exists for the given name');
    }

    public function testHasShortcut()
    {
        $this->initializeOptions();

        $definition = new InputDefinition([$this->foo]);
        $this->assertTrue($definition->hasShortcut('f'), '->hasShortcut() returns true if a InputOption exists for the given shortcut');
        $this->assertFalse($definition->hasShortcut('b'), '->hasShortcut() returns false if a InputOption exists for the given shortcut');
    }

    public function testGetOptionForShortcut()
    {
        $this->initializeOptions();

        $definition = new InputDefinition([$this->foo]);
        $this->assertSame($this->foo, $definition->getOptionForShortcut('f'), '->getOptionForShortcut() returns a InputOption by its shortcut');
    }

    public function testGetOptionForMultiShortcut()
    {
        $this->initializeOptions();

        $definition = new InputDefinition([$this->multi]);
        $this->assertSame($this->multi, $definition->getOptionForShortcut('m'), '->getOptionForShortcut() returns a InputOption by its shortcut');
        $this->assertSame($this->multi, $definition->getOptionForShortcut('mmm'), '->getOptionForShortcut() returns a InputOption by its shortcut');
    }

    public function testGetOptionForInvalidShortcut()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "-l" option does not exist.');
        $this->initializeOptions();

        $definition = new InputDefinition([$this->foo]);
        $definition->getOptionForShortcut('l');
    }

    public function testGetOptionDefaults()
    {
        $definition = new InputDefinition([
            new InputOption('foo1', null, InputOption::VALUE_NONE),
            new InputOption('foo2', null, InputOption::VALUE_REQUIRED),
            new InputOption('foo3', null, InputOption::VALUE_REQUIRED, '', 'default'),
            new InputOption('foo4', null, InputOption::VALUE_OPTIONAL),
            new InputOption('foo5', null, InputOption::VALUE_OPTIONAL, '', 'default'),
            new InputOption('foo6', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY),
            new InputOption('foo7', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, '', [1, 2]),
        ]);
        $defaults = [
            'foo1' => false,
            'foo2' => null,
            'foo3' => 'default',
            'foo4' => null,
            'foo5' => 'default',
            'foo6' => [],
            'foo7' => [1, 2],
        ];
        $this->assertSame($defaults, $definition->getOptionDefaults(), '->getOptionDefaults() returns the default values for all options');
    }

    /**
     * @dataProvider getGetSynopsisData
     */
    public function testGetSynopsis(InputDefinition $definition, ?string $expectedSynopsis, ?string $message = null)
    {
        $this->assertSame($expectedSynopsis, $definition->getSynopsis(), $message ? '->getSynopsis() '.$message : '');
    }

    public static function getGetSynopsisData()
    {
        return [
            [new InputDefinition([new InputOption('foo')]), '[--foo]', 'puts optional options in square brackets'],
            [new InputDefinition([new InputOption('foo', 'f')]), '[-f|--foo]', 'separates shortcut with a pipe'],
            [new InputDefinition([new InputOption('foo', 'f', InputOption::VALUE_REQUIRED)]), '[-f|--foo FOO]', 'uses shortcut as value placeholder'],
            [new InputDefinition([new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL)]), '[-f|--foo [FOO]]', 'puts optional values in square brackets'],

            [new InputDefinition([new InputArgument('foo', InputArgument::REQUIRED)]), '<foo>', 'puts arguments in angle brackets'],
            [new InputDefinition([new InputArgument('foo')]), '[<foo>]', 'puts optional arguments in square brackets'],
            [new InputDefinition([new InputArgument('foo'), new InputArgument('bar')]), '[<foo> [<bar>]]', 'chains optional arguments inside brackets'],
            [new InputDefinition([new InputArgument('foo', InputArgument::IS_ARRAY)]), '[<foo>...]', 'uses an ellipsis for array arguments'],
            [new InputDefinition([new InputArgument('foo', InputArgument::REQUIRED | InputArgument::IS_ARRAY)]), '<foo>...', 'uses an ellipsis for required array arguments'],

            [new InputDefinition([new InputOption('foo'), new InputArgument('foo', InputArgument::REQUIRED)]), '[--foo] [--] <foo>', 'puts [--] between options and arguments'],

            [new InputDefinition([new InputOption('deprecated', null, InputOption::DEPRECATED)]), '[--deprecated]', 'puts deprecated optional options in square brackets'],
            [
                new InputDefinition([new InputOption('foo'), new InputOption('deprecated', null, InputOption::DEPRECATED)]),
                '[--foo] [--deprecated]',
                'puts deprecated optional options in square brackets',
            ],

            [new InputDefinition([new InputOption('hidden', null, InputOption::HIDDEN)]), '', 'hidden option is not visible'],
            [new InputDefinition([new InputOption('foo'), new InputOption('hidden', null, InputOption::HIDDEN)]), '[--foo]', 'hidden option is not visible'],
        ];
    }

    public function testGetShortSynopsis()
    {
        $definition = new InputDefinition([new InputOption('foo'), new InputOption('bar'), new InputArgument('cat')]);
        $this->assertSame('[options] [--] [<cat>]', $definition->getSynopsis(true), '->getSynopsis(true) groups options in [options]');
    }

    protected function initializeArguments()
    {
        $this->foo = new InputArgument('foo');
        $this->bar = new InputArgument('bar');
        $this->foo1 = new InputArgument('foo');
        $this->foo2 = new InputArgument('foo2', InputArgument::REQUIRED);
    }

    protected function initializeOptions()
    {
        $this->foo = new InputOption('foo', 'f');
        $this->bar = new InputOption('bar', 'b');
        $this->foo1 = new InputOption('fooBis', 'f');
        $this->foo2 = new InputOption('foo', 'p');
        $this->multi = new InputOption('multi', 'm|mm|mmm');
    }
}
