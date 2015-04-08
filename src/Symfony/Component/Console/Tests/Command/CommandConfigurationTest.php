<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\CommandConfiguration;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class CommandConfigurationTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $command = new Command('foo');
        $configuration = new CommandConfiguration($command);
        $this->assertSame($command, $configuration->getCommand(), '__construct() takes the command as its first argument');
    }

    /**
     * @expectedException        \LogicException
     * @expectedExceptionMessage No command was set
     */
    public function testCommandMustBeSet()
    {
        $configuration = new CommandConfiguration();
        $configuration->getCommand();
    }

    /**
     * @expectedException        \LogicException
     * @expectedExceptionMessage The command must be a Command instance or a callable returning a Command instance
     */
    public function testSetInvalidCommand()
    {
        $configuration = new CommandConfiguration();
        $configuration->setCommand(new \stdClass());
    }

    public function testSetGetCommandInstance()
    {
        $commandInstance = new Command('foo');
        $configuration = new CommandConfiguration();
        $configuration->setCommand($commandInstance);
        $this->assertSame($commandInstance, $configuration->getCommand());
    }

    public function testSetGetCommandResolver()
    {
        $commandInstance = new Command('foo');
        $configuration = new CommandConfiguration();
        $configuration->setCommand(function () use ($commandInstance) {
            return $commandInstance;
        });
        $this->assertSame($commandInstance, $configuration->getCommand());
    }

    public function testCommandResolverOnlyCalledOnce()
    {
        $configuration = new CommandConfiguration();
        $configuration->setCommand(function () {
            return new Command('foo');
        });
        $this->assertSame($configuration->getCommand(), $configuration->getCommand());
    }

    /**
     * @expectedException        \LogicException
     * @expectedExceptionMessage The command must be a Command instance or a callable returning a Command instance
     */
    public function testCommandResolverMustReturnCommand()
    {
        $configuration = new CommandConfiguration();
        $configuration->setCommand(function () {
            return;
        });
        $configuration->getCommand();
    }

    public function testSetGetDefinition()
    {
        $configuration = new CommandConfiguration();
        $ret = $configuration->setDefinition($definition = new InputDefinition());
        $this->assertEquals($configuration, $ret, '->setDefinition() implements a fluent interface');
        $this->assertEquals($definition, $configuration->getDefinition(), '->setDefinition() sets the current InputDefinition instance');
        $configuration->setDefinition(array(new InputArgument('foo'), new InputOption('bar')));
        $this->assertTrue($configuration->getDefinition()->hasArgument('foo'), '->setDefinition() also takes an array of InputArguments and InputOptions as an argument');
        $this->assertTrue($configuration->getDefinition()->hasOption('bar'), '->setDefinition() also takes an array of InputArguments and InputOptions as an argument');
        $configuration->setDefinition(new InputDefinition());
    }

    public function testAddArgument()
    {
        $configuration = new CommandConfiguration();
        $ret = $configuration->addArgument('foo');
        $this->assertSame($configuration, $ret, '->addArgument() implements a fluent interface');
        $this->assertTrue($configuration->getDefinition()->hasArgument('foo'), '->addArgument() adds an argument to the command');
    }

    public function testAddOption()
    {
        $configuration = new CommandConfiguration();
        $ret = $configuration->addOption('foo');
        $this->assertSame($configuration, $ret, '->addOption() implements a fluent interface');
        $this->assertTrue($configuration->getDefinition()->hasOption('foo'), '->addOption() adds an option to the command');
    }

    public function testGetNamespaceGetNameSetName()
    {
        $configuration = new CommandConfiguration();
        $this->assertNull($configuration->getName(), '->getName() returns the command name');
        $configuration->setName('foo');
        $this->assertEquals('foo', $configuration->getName(), '->setName() sets the command name');

        $ret = $configuration->setName('foobar:bar');
        $this->assertSame($configuration, $ret, '->setName() implements a fluent interface');
        $this->assertEquals('foobar:bar', $configuration->getName(), '->setName() sets the command name');
    }

    /**
     * @dataProvider provideInvalidCommandNames
     */
    public function testInvalidCommandNames($name)
    {
        $this->setExpectedException('InvalidArgumentException', sprintf('Command name "%s" is invalid.', $name));

        $configuration = new CommandConfiguration();
        $configuration->setName($name);
    }

    public function provideInvalidCommandNames()
    {
        return array(
            array(''),
            array('foo:'),
        );
    }

    public function testGetSetDescription()
    {
        $configuration = new CommandConfiguration();
        $ret = $configuration->setDescription('description1');
        $this->assertSame($configuration, $ret, '->setDescription() implements a fluent interface');
        $this->assertEquals('description1', $configuration->getDescription(), '->setDescription() sets the description');
    }

    public function testGetSetProcessTitle()
    {
        $configuration = new CommandConfiguration();
        $ret = $configuration->setProcessTitle('foo');
        $this->assertSame($configuration, $ret, '->setProcessTitle() implements a fluent interface');
        $this->assertEquals('foo', $configuration->getProcessTitle(), '->setProcessTitle() sets the process title');
    }

    public function testGetSetHelp()
    {
        $configuration = new CommandConfiguration();
        $ret = $configuration->setHelp('help1');
        $this->assertSame($configuration, $ret, '->setHelp() implements a fluent interface');
        $this->assertEquals('help1', $configuration->getHelp(), '->setHelp() sets the help');
    }

    public function testGetProcessedHelp()
    {
        $configuration = new CommandConfiguration();
        $configuration->setName('namespace:name');
        $configuration->setHelp('The %command.name% command does... Example: php %command.full_name%.');
        $this->assertContains('The namespace:name command does...', $configuration->getProcessedHelp(), '->getProcessedHelp() replaces %command.name% correctly');
        $this->assertNotContains('%command.full_name%', $configuration->getProcessedHelp(), '->getProcessedHelp() replaces %command.full_name%');
    }

    public function testGetSetAliases()
    {
        $configuration = new CommandConfiguration();
        $ret = $configuration->setAliases(array('name'));
        $this->assertSame($configuration, $ret, '->setAliases() implements a fluent interface');
        $this->assertEquals(array('name'), $configuration->getAliases(), '->setAliases() sets the aliases');
    }

    public function testGetSynopsis()
    {
        $configuration = new CommandConfiguration();
        $configuration->setName('namespace:name');
        $configuration->addOption('foo');
        $configuration->addArgument('foo');
        $this->assertEquals('namespace:name [--foo] [foo]', $configuration->getSynopsis(), '->getSynopsis() returns the synopsis');
    }
}
