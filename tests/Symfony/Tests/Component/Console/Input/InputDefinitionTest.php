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

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class InputDefinitionTest extends \PHPUnit_Framework_TestCase
{
    protected static $fixtures;

    protected $foo, $bar, $foo1, $foo2;

    public static function setUpBeforeClass()
    {
        self::$fixtures = __DIR__.'/../Fixtures/';
    }

    public function testConstructor()
    {
        $this->initializeArguments();

        $definition = new InputDefinition();
        $this->assertEquals(array(), $definition->getArguments(), '__construct() creates a new InputDefinition object');

        $definition = new InputDefinition(array($this->foo, $this->bar));
        $this->assertEquals(array('foo' => $this->foo, 'bar' => $this->bar), $definition->getArguments(), '__construct() takes an array of InputArgument objects as its first argument');

        $this->initializeOptions();

        $definition = new InputDefinition();
        $this->assertEquals(array(), $definition->getOptions(), '__construct() creates a new InputDefinition object');

        $definition = new InputDefinition(array($this->foo, $this->bar));
        $this->assertEquals(array('foo' => $this->foo, 'bar' => $this->bar), $definition->getOptions(), '__construct() takes an array of InputOption objects as its first argument');
    }

    public function testSetArguments()
    {
        $this->initializeArguments();

        $definition = new InputDefinition();
        $definition->setArguments(array($this->foo));
        $this->assertEquals(array('foo' => $this->foo), $definition->getArguments(), '->setArguments() sets the array of InputArgument objects');
        $definition->setArguments(array($this->bar));

        $this->assertEquals(array('bar' => $this->bar), $definition->getArguments(), '->setArguments() clears all InputArgument objects');
    }

    public function testAddArguments()
    {
        $this->initializeArguments();

        $definition = new InputDefinition();
        $definition->addArguments(array($this->foo));
        $this->assertEquals(array('foo' => $this->foo), $definition->getArguments(), '->addArguments() adds an array of InputArgument objects');
        $definition->addArguments(array($this->bar));
        $this->assertEquals(array('foo' => $this->foo, 'bar' => $this->bar), $definition->getArguments(), '->addArguments() does not clear existing InputArgument objects');
    }

    public function testAddArgument()
    {
        $this->initializeArguments();

        $definition = new InputDefinition();
        $definition->addArgument($this->foo);
        $this->assertEquals(array('foo' => $this->foo), $definition->getArguments(), '->addArgument() adds a InputArgument object');
        $definition->addArgument($this->bar);
        $this->assertEquals(array('foo' => $this->foo, 'bar' => $this->bar), $definition->getArguments(), '->addArgument() adds a InputArgument object');

        // arguments must have different names
        try {
            $definition->addArgument($this->foo1);
            $this->fail('->addArgument() throws a Exception if another argument is already registered with the same name');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\Exception', $e, '->addArgument() throws a Exception if another argument is already registered with the same name');
            $this->assertEquals('An argument with name "foo" already exist.', $e->getMessage());
        }

        // cannot add a parameter after an array parameter
        $definition->addArgument(new InputArgument('fooarray', InputArgument::IS_ARRAY));
        try {
            $definition->addArgument(new InputArgument('anotherbar'));
            $this->fail('->addArgument() throws a Exception if there is an array parameter already registered');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\Exception', $e, '->addArgument() throws a Exception if there is an array parameter already registered');
            $this->assertEquals('Cannot add an argument after an array argument.', $e->getMessage());
        }

        // cannot add a required argument after an optional one
        $definition = new InputDefinition();
        $definition->addArgument($this->foo);
        try {
            $definition->addArgument($this->foo2);
            $this->fail('->addArgument() throws an exception if you try to add a required argument after an optional one');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\Exception', $e, '->addArgument() throws an exception if you try to add a required argument after an optional one');
            $this->assertEquals('Cannot add a required argument after an optional one.', $e->getMessage());
        }
    }

    public function testGetArgument()
    {
        $this->initializeArguments();

        $definition = new InputDefinition();
        $definition->addArguments(array($this->foo));
        $this->assertEquals($this->foo, $definition->getArgument('foo'), '->getArgument() returns a InputArgument by its name');
        try {
            $definition->getArgument('bar');
            $this->fail('->getArgument() throws an exception if the InputArgument name does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\Exception', $e, '->getArgument() throws an exception if the InputArgument name does not exist');
            $this->assertEquals('The "bar" argument does not exist.', $e->getMessage());
        }
    }

    public function testHasArgument()
    {
        $this->initializeArguments();

        $definition = new InputDefinition();
        $definition->addArguments(array($this->foo));
        $this->assertTrue($definition->hasArgument('foo'), '->hasArgument() returns true if a InputArgument exists for the given name');
        $this->assertFalse($definition->hasArgument('bar'), '->hasArgument() returns false if a InputArgument exists for the given name');
    }

    public function testGetArgumentRequiredCount()
    {
        $this->initializeArguments();

        $definition = new InputDefinition();
        $definition->addArgument($this->foo2);
        $this->assertEquals(1, $definition->getArgumentRequiredCount(), '->getArgumentRequiredCount() returns the number of required arguments');
        $definition->addArgument($this->foo);
        $this->assertEquals(1, $definition->getArgumentRequiredCount(), '->getArgumentRequiredCount() returns the number of required arguments');
    }

    public function testGetArgumentCount()
    {
        $this->initializeArguments();

        $definition = new InputDefinition();
        $definition->addArgument($this->foo2);
        $this->assertEquals(1, $definition->getArgumentCount(), '->getArgumentCount() returns the number of arguments');
        $definition->addArgument($this->foo);
        $this->assertEquals(2, $definition->getArgumentCount(), '->getArgumentCount() returns the number of arguments');
    }

    public function testGetArgumentDefaults()
    {
        $definition = new InputDefinition(array(
            new InputArgument('foo1', InputArgument::OPTIONAL),
            new InputArgument('foo2', InputArgument::OPTIONAL, '', 'default'),
            new InputArgument('foo3', InputArgument::OPTIONAL | InputArgument::IS_ARRAY),
        //  new InputArgument('foo4', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, '', array(1, 2)),
        ));
        $this->assertEquals(array('foo1' => null, 'foo2' => 'default', 'foo3' => array()), $definition->getArgumentDefaults(), '->getArgumentDefaults() return the default values for each argument');

        $definition = new InputDefinition(array(
            new InputArgument('foo4', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, '', array(1, 2)),
        ));
        $this->assertEquals(array('foo4' => array(1, 2)), $definition->getArgumentDefaults(), '->getArgumentDefaults() return the default values for each argument');
    }

    public function testSetOptions()
    {
        $this->initializeOptions();

        $definition = new InputDefinition(array($this->foo));
        $this->assertEquals(array('foo' => $this->foo), $definition->getOptions(), '->setOptions() sets the array of InputOption objects');
        $definition->setOptions(array($this->bar));
        $this->assertEquals(array('bar' => $this->bar), $definition->getOptions(), '->setOptions() clears all InputOption objects');
        try {
            $definition->getOptionForShortcut('f');
            $this->fail('->setOptions() clears all InputOption objects');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\Exception', $e, '->setOptions() clears all InputOption objects');
            $this->assertEquals('The "-f" option does not exist.', $e->getMessage());
        }
    }

    public function testAddOptions()
    {
        $this->initializeOptions();

        $definition = new InputDefinition(array($this->foo));
        $this->assertEquals(array('foo' => $this->foo), $definition->getOptions(), '->addOptions() adds an array of InputOption objects');
        $definition->addOptions(array($this->bar));
        $this->assertEquals(array('foo' => $this->foo, 'bar' => $this->bar), $definition->getOptions(), '->addOptions() does not clear existing InputOption objects');
    }

    public function testAddOption()
    {
        $this->initializeOptions();

        $definition = new InputDefinition();
        $definition->addOption($this->foo);
        $this->assertEquals(array('foo' => $this->foo), $definition->getOptions(), '->addOption() adds a InputOption object');
        $definition->addOption($this->bar);
        $this->assertEquals(array('foo' => $this->foo, 'bar' => $this->bar), $definition->getOptions(), '->addOption() adds a InputOption object');
        try {
            $definition->addOption($this->foo2);
            $this->fail('->addOption() throws a Exception if the another option is already registered with the same name');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\Exception', $e, '->addOption() throws a Exception if the another option is already registered with the same name');
            $this->assertEquals('An option named "foo" already exist.', $e->getMessage());
        }
        try {
            $definition->addOption($this->foo1);
            $this->fail('->addOption() throws a Exception if the another option is already registered with the same shortcut');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\Exception', $e, '->addOption() throws a Exception if the another option is already registered with the same shortcut');
            $this->assertEquals('An option with shortcut "f" already exist.', $e->getMessage());
        }
    }

    public function testGetOption()
    {
        $this->initializeOptions();

        $definition = new InputDefinition(array($this->foo));
        $this->assertEquals($this->foo, $definition->getOption('foo'), '->getOption() returns a InputOption by its name');
        try {
            $definition->getOption('bar');
            $this->fail('->getOption() throws an exception if the option name does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\Exception', $e, '->getOption() throws an exception if the option name does not exist');
            $this->assertEquals('The "--bar" option does not exist.', $e->getMessage());
        }
    }

    public function testHasOption()
    {
        $this->initializeOptions();

        $definition = new InputDefinition(array($this->foo));
        $this->assertTrue($definition->hasOption('foo'), '->hasOption() returns true if a InputOption exists for the given name');
        $this->assertFalse($definition->hasOption('bar'), '->hasOption() returns false if a InputOption exists for the given name');
    }

    public function testHasShortcut()
    {
        $this->initializeOptions();

        $definition = new InputDefinition(array($this->foo));
        $this->assertTrue($definition->hasShortcut('f'), '->hasShortcut() returns true if a InputOption exists for the given shortcut');
        $this->assertFalse($definition->hasShortcut('b'), '->hasShortcut() returns false if a InputOption exists for the given shortcut');
    }

    public function testGetOptionForShortcut()
    {
        $this->initializeOptions();

        $definition = new InputDefinition(array($this->foo));
        $this->assertEquals($this->foo, $definition->getOptionForShortcut('f'), '->getOptionForShortcut() returns a InputOption by its shortcut');
        try {
            $definition->getOptionForShortcut('l');
            $this->fail('->getOption() throws an exception if the shortcut does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\Exception', $e, '->getOption() throws an exception if the shortcut does not exist');
            $this->assertEquals('The "-l" option does not exist.', $e->getMessage());
        }
    }

    public function testGetOptionDefaults()
    {
        $definition = new InputDefinition(array(
            new InputOption('foo1', null, InputOption::VALUE_NONE),
            new InputOption('foo2', null, InputOption::VALUE_REQUIRED),
            new InputOption('foo3', null, InputOption::VALUE_REQUIRED, '', 'default'),
            new InputOption('foo4', null, InputOption::VALUE_OPTIONAL),
            new InputOption('foo5', null, InputOption::VALUE_OPTIONAL, '', 'default'),
            new InputOption('foo6', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY),
            new InputOption('foo7', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, '', array(1, 2)),
        ));
        $defaults = array(
            'foo1' => null,
            'foo2' => null,
            'foo3' => 'default',
            'foo4' => null,
            'foo5' => 'default',
            'foo6' => array(),
            'foo7' => array(1, 2),
        );
        $this->assertEquals($defaults, $definition->getOptionDefaults(), '->getOptionDefaults() returns the default values for all options');
    }

    public function testGetSynopsis()
    {
        $definition = new InputDefinition(array(new InputOption('foo')));
        $this->assertEquals('[--foo]', $definition->getSynopsis(), '->getSynopsis() returns a synopsis of arguments and options');
        $definition = new InputDefinition(array(new InputOption('foo', 'f')));
        $this->assertEquals('[-f|--foo]', $definition->getSynopsis(), '->getSynopsis() returns a synopsis of arguments and options');
        $definition = new InputDefinition(array(new InputOption('foo', 'f', InputOption::VALUE_REQUIRED)));
        $this->assertEquals('[-f|--foo="..."]', $definition->getSynopsis(), '->getSynopsis() returns a synopsis of arguments and options');
        $definition = new InputDefinition(array(new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL)));
        $this->assertEquals('[-f|--foo[="..."]]', $definition->getSynopsis(), '->getSynopsis() returns a synopsis of arguments and options');

        $definition = new InputDefinition(array(new InputArgument('foo')));
        $this->assertEquals('[foo]', $definition->getSynopsis(), '->getSynopsis() returns a synopsis of arguments and options');
        $definition = new InputDefinition(array(new InputArgument('foo', InputArgument::REQUIRED)));
        $this->assertEquals('foo', $definition->getSynopsis(), '->getSynopsis() returns a synopsis of arguments and options');
        $definition = new InputDefinition(array(new InputArgument('foo', InputArgument::IS_ARRAY)));
        $this->assertEquals('[foo1] ... [fooN]', $definition->getSynopsis(), '->getSynopsis() returns a synopsis of arguments and options');
        $definition = new InputDefinition(array(new InputArgument('foo', InputArgument::REQUIRED | InputArgument::IS_ARRAY)));
        $this->assertEquals('foo1 ... [fooN]', $definition->getSynopsis(), '->getSynopsis() returns a synopsis of arguments and options');
    }

    public function testAsText()
    {
        $definition = new InputDefinition(array(
            new InputArgument('foo', InputArgument::OPTIONAL, 'The foo argument'),
            new InputArgument('baz', InputArgument::OPTIONAL, 'The baz argument', true),
            new InputArgument('bar', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'The bar argument', array('bar')),
            new InputOption('foo', 'f', InputOption::VALUE_REQUIRED, 'The foo option'),
            new InputOption('baz', null, InputOption::VALUE_OPTIONAL, 'The baz option', false),
            new InputOption('bar', 'b', InputOption::VALUE_OPTIONAL, 'The bar option', 'bar'),
        ));
        $this->assertStringEqualsFile(self::$fixtures.'/definition_astext.txt', $definition->asText(), '->asText() returns a textual representation of the InputDefinition');
    }

    public function testAsXml()
    {
        $definition = new InputDefinition(array(
            new InputArgument('foo', InputArgument::OPTIONAL, 'The foo argument'),
            new InputArgument('baz', InputArgument::OPTIONAL, 'The baz argument', true),
            new InputArgument('bar', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'The bar argument', array('bar')),
            new InputOption('foo', 'f', InputOption::VALUE_REQUIRED, 'The foo option'),
            new InputOption('baz', null, InputOption::VALUE_OPTIONAL, 'The baz option', false),
            new InputOption('bar', 'b', InputOption::VALUE_OPTIONAL, 'The bar option', 'bar'),
        ));
        $this->assertXmlStringEqualsXmlFile(self::$fixtures.'/definition_asxml.txt', $definition->asXml(), '->asText() returns a textual representation of the InputDefinition');
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
    }
}
