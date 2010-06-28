<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Console;

use Symfony\Components\Console\Application;
use Symfony\Components\Console\Input\ArrayInput;
use Symfony\Components\Console\Output\Output;
use Symfony\Components\Console\Output\StreamOutput;
use Symfony\Components\Console\Tester\ApplicationTester;

class ApplicationTest extends \PHPUnit_Framework_TestCase
{
    static protected $fixturesPath;

    static public function setUpBeforeClass()
    {
        self::$fixturesPath = realpath(__DIR__.'/Fixtures/');
        require_once self::$fixturesPath.'/FooCommand.php';
        require_once self::$fixturesPath.'/Foo1Command.php';
        require_once self::$fixturesPath.'/Foo2Command.php';
    }

    public function testConstructor()
    {
        $application = new Application('foo', 'bar');
        $this->assertEquals('foo', $application->getName(), '__construct() takes the application name as its first argument');
        $this->assertEquals('bar', $application->getVersion(), '__construct() takes the application version as its first argument');
        $this->assertEquals(array('help', 'list'), array_keys($application->getCommands()), '__construct() registered the help and list commands by default');
    }

    public function testSetGetName()
    {
        $application = new Application();
        $application->setName('foo');
        $this->assertEquals('foo', $application->getName(), '->setName() sets the name of the application');
    }

    public function testSetGetVersion()
    {
        $application = new Application();
        $application->setVersion('bar');
        $this->assertEquals('bar', $application->getVersion(), '->setVersion() sets the version of the application');
    }

    public function testGetLongVersion()
    {
        $application = new Application('foo', 'bar');
        $this->assertEquals('<info>foo</info> version <comment>bar</comment>', $application->getLongVersion(), '->getLongVersion() returns the long version of the application');
    }

    public function testHelp()
    {
        $application = new Application();
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_gethelp.txt', $application->getHelp(), '->setHelp() returns a help message');
    }

    public function testGetCommands()
    {
        $application = new Application();
        $commands = $application->getCommands();
        $this->assertEquals('Symfony\\Components\\Console\\Command\\HelpCommand', get_class($commands['help']), '->getCommands() returns the registered commands');

        $application->addCommand(new \FooCommand());
        $commands = $application->getCommands('foo');
        $this->assertEquals(1, count($commands), '->getCommands() takes a namespace as its first argument');
    }

    public function testRegister()
    {
        $application = new Application();
        $command = $application->register('foo');
        $this->assertEquals('foo', $command->getName(), '->register() registers a new command');
    }

    public function testAddCommand()
    {
        $application = new Application();
        $application->addCommand($foo = new \FooCommand());
        $commands = $application->getCommands();
        $this->assertEquals($foo, $commands['foo:bar'], '->addCommand() registers a command');

        $application = new Application();
        $application->addCommands(array($foo = new \FooCommand(), $foo1 = new \Foo1Command()));
        $commands = $application->getCommands();
        $this->assertEquals(array($foo, $foo1), array($commands['foo:bar'], $commands['foo:bar1']), '->addCommands() registers an array of commands');
    }

    public function testHasGetCommand()
    {
        $application = new Application();
        $this->assertTrue($application->hasCommand('list'), '->hasCommand() returns true if a named command is registered');
        $this->assertFalse($application->hasCommand('afoobar'), '->hasCommand() returns false if a named command is not registered');

        $application->addCommand($foo = new \FooCommand());
        $this->assertTrue($application->hasCommand('afoobar'), '->hasCommand() returns true if an alias is registered');
        $this->assertEquals($foo, $application->getCommand('foo:bar'), '->getCommand() returns a command by name');
        $this->assertEquals($foo, $application->getCommand('afoobar'), '->getCommand() returns a command by alias');

        try {
            $application->getCommand('foofoo');
            $this->fail('->getCommand() throws an \InvalidArgumentException if the command does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->getCommand() throws an \InvalidArgumentException if the command does not exist');
            $this->assertEquals('The command "foofoo" does not exist.', $e->getMessage(), '->getCommand() throws an \InvalidArgumentException if the command does not exist');
        }

        $application = new TestApplication();
        $application->addCommand($foo = new \FooCommand());
        $application->setWantHelps();
        $command = $application->getCommand('foo:bar');
        $this->assertEquals('Symfony\Components\Console\Command\HelpCommand', get_class($command), '->getCommand() returns the help command if --help is provided as the input');
    }

    public function testGetNamespaces()
    {
        $application = new TestApplication();
        $application->addCommand(new \FooCommand());
        $application->addCommand(new \Foo1Command());
        $this->assertEquals(array('foo'), $application->getNamespaces(), '->getNamespaces() returns an array of unique used namespaces');
    }

    public function testFindNamespace()
    {
        $application = new TestApplication();
        $application->addCommand(new \FooCommand());
        $this->assertEquals('foo', $application->findNamespace('foo'), '->findNamespace() returns the given namespace if it exists');
        $this->assertEquals('foo', $application->findNamespace('f'), '->findNamespace() finds a namespace given an abbreviation');
        $application->addCommand(new \Foo2Command());
        $this->assertEquals('foo', $application->findNamespace('foo'), '->findNamespace() returns the given namespace if it exists');
        try {
            $application->findNamespace('f');
            $this->fail('->findNamespace() throws an \InvalidArgumentException if the abbreviation is ambiguous');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->findNamespace() throws an \InvalidArgumentException if the abbreviation is ambiguous');
            $this->assertEquals('The namespace "f" is ambiguous (foo, foo1).', $e->getMessage(), '->findNamespace() throws an \InvalidArgumentException if the abbreviation is ambiguous');
        }

        try {
            $application->findNamespace('bar');
            $this->fail('->findNamespace() throws an \InvalidArgumentException if no command is in the given namespace');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->findNamespace() throws an \InvalidArgumentException if no command is in the given namespace');
            $this->assertEquals('There are no commands defined in the "bar" namespace.', $e->getMessage(), '->findNamespace() throws an \InvalidArgumentException if no command is in the given namespace');
        }
    }

    public function testFindCommand()
    {
        $application = new TestApplication();
        $application->addCommand(new \FooCommand());
        $this->assertEquals('FooCommand', get_class($application->findCommand('foo:bar')), '->findCommand() returns a command if its name exists');
        $this->assertEquals('Symfony\Components\Console\Command\HelpCommand', get_class($application->findCommand('h')), '->findCommand() returns a command if its name exists');
        $this->assertEquals('FooCommand', get_class($application->findCommand('f:bar')), '->findCommand() returns a command if the abbreviation for the namespace exists');
        $this->assertEquals('FooCommand', get_class($application->findCommand('f:b')), '->findCommand() returns a command if the abbreviation for the namespace and the command name exist');
        $this->assertEquals('FooCommand', get_class($application->findCommand('a')), '->findCommand() returns a command if the abbreviation exists for an alias');

        $application->addCommand(new \Foo1Command());
        $application->addCommand(new \Foo2Command());

        try {
            $application->findCommand('f');
            $this->fail('->findCommand() throws an \InvalidArgumentException if the abbreviation is ambiguous for a namespace');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->findCommand() throws an \InvalidArgumentException if the abbreviation is ambiguous for a namespace');
            $this->assertEquals('Command "f" is not defined.', $e->getMessage(), '->findCommand() throws an \InvalidArgumentException if the abbreviation is ambiguous for a namespace');
        }

        try {
            $application->findCommand('a');
            $this->fail('->findCommand() throws an \InvalidArgumentException if the abbreviation is ambiguous for an alias');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->findCommand() throws an \InvalidArgumentException if the abbreviation is ambiguous for an alias');
            $this->assertEquals('Command "a" is ambiguous (afoobar, afoobar1 and 1 more).', $e->getMessage(), '->findCommand() throws an \InvalidArgumentException if the abbreviation is ambiguous for an alias');
        }

        try {
            $application->findCommand('foo:b');
            $this->fail('->findCommand() throws an \InvalidArgumentException if the abbreviation is ambiguous for a command');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->findCommand() throws an \InvalidArgumentException if the abbreviation is ambiguous for a command');
            $this->assertEquals('Command "foo:b" is ambiguous (foo:bar, foo:bar1).', $e->getMessage(), '->findCommand() throws an \InvalidArgumentException if the abbreviation is ambiguous for a command');
        }
    }

    public function testSetCatchExceptions()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);

        $application->setCatchExceptions(true);
        $tester->run(array('command' => 'foo'));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception1.txt', $tester->getDisplay(), '->setCatchExceptions() sets the catch exception flag');

        $application->setCatchExceptions(false);
        try {
            $tester->run(array('command' => 'foo'));
            $this->fail('->setCatchExceptions() sets the catch exception flag');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\Exception', $e, '->setCatchExceptions() sets the catch exception flag');
            $this->assertEquals('Command "foo" is not defined.', $e->getMessage(), '->setCatchExceptions() sets the catch exception flag');
        }
    }

    public function testAsText()
    {
        $application = new Application();
        $application->addCommand(new \FooCommand);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_astext1.txt', $application->asText(), '->asText() returns a text representation of the application');
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_astext2.txt', $application->asText('foo'), '->asText() returns a text representation of the application');
    }

    public function testAsXml()
    {
        $application = new Application();
        $application->addCommand(new \FooCommand);
        $this->assertXmlStringEqualsXmlFile(self::$fixturesPath.'/application_asxml1.txt', $application->asXml(), '->asXml() returns an XML representation of the application');
        $this->assertXmlStringEqualsXmlFile(self::$fixturesPath.'/application_asxml2.txt', $application->asXml('foo'), '->asXml() returns an XML representation of the application');
    }

    public function testRenderException()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);

        $tester->run(array('command' => 'foo'));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception1.txt', $tester->getDisplay(), '->renderException() renders a pretty exception');

        $tester->run(array('command' => 'foo'), array('verbosity' => Output::VERBOSITY_VERBOSE));
        $this->assertRegExp('/Exception trace/', $tester->getDisplay(), '->renderException() renders a pretty exception with a stack trace when verbosity is verbose');

        $tester->run(array('command' => 'list', '--foo' => true));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception2.txt', $tester->getDisplay(), '->renderException() renders the command synopsis when an exception occurs in the context of a command');
    }

    public function testRun()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->addCommand($command = new \Foo1Command());
        $_SERVER['argv'] = array('cli.php', 'foo:bar1');

        ob_start();
        $application->run();
        ob_end_clean();

        $this->assertEquals('Symfony\Components\Console\Input\ArgvInput', get_class($command->input), '->run() creates an ArgvInput by default if none is given');
        $this->assertEquals('Symfony\Components\Console\Output\ConsoleOutput', get_class($command->output), '->run() creates a ConsoleOutput by default if none is given');

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $tester = new ApplicationTester($application);
        $tester->run(array());
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run1.txt', $tester->getDisplay(), '->run() runs the list command if no argument is passed');

        $tester->run(array('--help' => true));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run2.txt', $tester->getDisplay(), '->run() runs the help command if --help is passed');

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'list', '--help' => true));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run3.txt', $tester->getDisplay(), '->run() displays the help if --help is passed');

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $tester = new ApplicationTester($application);
        $tester->run(array('--color' => true));
        $this->assertTrue($tester->getOutput()->isDecorated(), '->run() forces color output if --color is passed');

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $tester = new ApplicationTester($application);
        $tester->run(array('--version' => true));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run4.txt', $tester->getDisplay(), '->run() displays the program version if --version is passed');

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'list', '--quiet' => true));
        $this->assertEquals('', $tester->getDisplay(), '->run() removes all output if --quiet is passed');

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'list', '--verbose' => true));
        $this->assertEquals(Output::VERBOSITY_VERBOSE, $tester->getOutput()->getVerbosity(), '->run() sets the output to verbose is --verbose is passed');

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->addCommand(new \FooCommand());
        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'foo:bar', '--no-interaction' => true));
        $this->assertEquals("called\n", $tester->getDisplay(), '->run() does not called interact() if --no-interaction is passed');
    }
}

class TestApplication extends Application
{
    public function setWantHelps()
    {
        $this->wantHelps = true;
    }
}
