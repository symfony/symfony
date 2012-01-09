<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Console;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Tester\ApplicationTester;

class ApplicationTest extends \PHPUnit_Framework_TestCase
{
    static protected $fixturesPath;

    static public function setUpBeforeClass()
    {
        self::$fixturesPath = realpath(__DIR__.'/Fixtures/');
        require_once self::$fixturesPath.'/FooCommand.php';
        require_once self::$fixturesPath.'/Foo1Command.php';
        require_once self::$fixturesPath.'/Foo2Command.php';
        require_once self::$fixturesPath.'/Foo3Command.php';
    }

    protected function normalize($text)
    {
        return str_replace(PHP_EOL, "\n", $text);
    }

    public function testConstructor()
    {
        $application = new Application('foo', 'bar');
        $this->assertEquals('foo', $application->getName(), '__construct() takes the application name as its first argument');
        $this->assertEquals('bar', $application->getVersion(), '__construct() takes the application version as its first argument');
        $this->assertEquals(array('help', 'list'), array_keys($application->all()), '__construct() registered the help and list commands by default');
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

    public function testAll()
    {
        $application = new Application();
        $commands = $application->all();
        $this->assertEquals('Symfony\\Component\\Console\\Command\\HelpCommand', get_class($commands['help']), '->all() returns the registered commands');

        $application->add(new \FooCommand());
        $commands = $application->all('foo');
        $this->assertEquals(1, count($commands), '->all() takes a namespace as its first argument');
    }

    public function testRegister()
    {
        $application = new Application();
        $command = $application->register('foo');
        $this->assertEquals('foo', $command->getName(), '->register() registers a new command');
    }

    public function testAdd()
    {
        $application = new Application();
        $application->add($foo = new \FooCommand());
        $commands = $application->all();
        $this->assertEquals($foo, $commands['foo:bar'], '->add() registers a command');

        $application = new Application();
        $application->addCommands(array($foo = new \FooCommand(), $foo1 = new \Foo1Command()));
        $commands = $application->all();
        $this->assertEquals(array($foo, $foo1), array($commands['foo:bar'], $commands['foo:bar1']), '->addCommands() registers an array of commands');
    }

    public function testHasGet()
    {
        $application = new Application();
        $this->assertTrue($application->has('list'), '->has() returns true if a named command is registered');
        $this->assertFalse($application->has('afoobar'), '->has() returns false if a named command is not registered');

        $application->add($foo = new \FooCommand());
        $this->assertTrue($application->has('afoobar'), '->has() returns true if an alias is registered');
        $this->assertEquals($foo, $application->get('foo:bar'), '->get() returns a command by name');
        $this->assertEquals($foo, $application->get('afoobar'), '->get() returns a command by alias');

        try {
            $application->get('foofoo');
            $this->fail('->get() throws an \InvalidArgumentException if the command does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->get() throws an \InvalidArgumentException if the command does not exist');
            $this->assertEquals('The command "foofoo" does not exist.', $e->getMessage(), '->get() throws an \InvalidArgumentException if the command does not exist');
        }

        $application = new Application();
        $application->add($foo = new \FooCommand());
        // simulate --help
        $r = new \ReflectionObject($application);
        $p = $r->getProperty('wantHelps');
        $p->setAccessible(true);
        $p->setValue($application, true);
        $command = $application->get('foo:bar');
        $this->assertEquals('Symfony\Component\Console\Command\HelpCommand', get_class($command), '->get() returns the help command if --help is provided as the input');
    }

    public function testGetNamespaces()
    {
        $application = new Application();
        $application->add(new \FooCommand());
        $application->add(new \Foo1Command());
        $this->assertEquals(array('foo'), $application->getNamespaces(), '->getNamespaces() returns an array of unique used namespaces');
    }

    public function testFindNamespace()
    {
        $application = new Application();
        $application->add(new \FooCommand());
        $this->assertEquals('foo', $application->findNamespace('foo'), '->findNamespace() returns the given namespace if it exists');
        $this->assertEquals('foo', $application->findNamespace('f'), '->findNamespace() finds a namespace given an abbreviation');
        $application->add(new \Foo2Command());
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

    public function testFind()
    {
        $application = new Application();
        $application->add(new \FooCommand());
        $this->assertEquals('FooCommand', get_class($application->find('foo:bar')), '->find() returns a command if its name exists');
        $this->assertEquals('Symfony\Component\Console\Command\HelpCommand', get_class($application->find('h')), '->find() returns a command if its name exists');
        $this->assertEquals('FooCommand', get_class($application->find('f:bar')), '->find() returns a command if the abbreviation for the namespace exists');
        $this->assertEquals('FooCommand', get_class($application->find('f:b')), '->find() returns a command if the abbreviation for the namespace and the command name exist');
        $this->assertEquals('FooCommand', get_class($application->find('a')), '->find() returns a command if the abbreviation exists for an alias');

        $application->add(new \Foo1Command());
        $application->add(new \Foo2Command());

        try {
            $application->find('f');
            $this->fail('->find() throws an \InvalidArgumentException if the abbreviation is ambiguous for a namespace');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->find() throws an \InvalidArgumentException if the abbreviation is ambiguous for a namespace');
            $this->assertEquals('Command "f" is not defined.', $e->getMessage(), '->find() throws an \InvalidArgumentException if the abbreviation is ambiguous for a namespace');
        }

        try {
            $application->find('a');
            $this->fail('->find() throws an \InvalidArgumentException if the abbreviation is ambiguous for an alias');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->find() throws an \InvalidArgumentException if the abbreviation is ambiguous for an alias');
            $this->assertEquals('Command "a" is ambiguous (afoobar, afoobar1 and 1 more).', $e->getMessage(), '->find() throws an \InvalidArgumentException if the abbreviation is ambiguous for an alias');
        }

        try {
            $application->find('foo:b');
            $this->fail('->find() throws an \InvalidArgumentException if the abbreviation is ambiguous for a command');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->find() throws an \InvalidArgumentException if the abbreviation is ambiguous for a command');
            $this->assertEquals('Command "foo:b" is ambiguous (foo:bar, foo:bar1).', $e->getMessage(), '->find() throws an \InvalidArgumentException if the abbreviation is ambiguous for a command');
        }
    }

    public function testSetCatchExceptions()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);

        $application->setCatchExceptions(true);
        $tester->run(array('command' => 'foo'), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception1.txt', $this->normalize($tester->getDisplay()), '->setCatchExceptions() sets the catch exception flag');

        $application->setCatchExceptions(false);
        try {
            $tester->run(array('command' => 'foo'), array('decorated' => false));
            $this->fail('->setCatchExceptions() sets the catch exception flag');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\Exception', $e, '->setCatchExceptions() sets the catch exception flag');
            $this->assertEquals('Command "foo" is not defined.', $e->getMessage(), '->setCatchExceptions() sets the catch exception flag');
        }
    }

    public function testAsText()
    {
        $application = new Application();
        $application->add(new \FooCommand);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_astext1.txt', str_replace(PHP_EOL, "\n", $application->asText()), '->asText() returns a text representation of the application');
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_astext2.txt', str_replace(PHP_EOL, "\n", $application->asText('foo')), '->asText() returns a text representation of the application');
    }

    public function testAsXml()
    {
        $application = new Application();
        $application->add(new \FooCommand);
        $this->assertXmlStringEqualsXmlFile(self::$fixturesPath.'/application_asxml1.txt', $application->asXml(), '->asXml() returns an XML representation of the application');
        $this->assertXmlStringEqualsXmlFile(self::$fixturesPath.'/application_asxml2.txt', $application->asXml('foo'), '->asXml() returns an XML representation of the application');
    }

    public function testRenderException()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);

        $tester->run(array('command' => 'foo'), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception1.txt', $this->normalize($tester->getDisplay()), '->renderException() renders a pretty exception');

        $tester->run(array('command' => 'foo'), array('decorated' => false, 'verbosity' => Output::VERBOSITY_VERBOSE));
        $this->assertRegExp('/Exception trace/', $this->normalize($tester->getDisplay()), '->renderException() renders a pretty exception with a stack trace when verbosity is verbose');

        $tester->run(array('command' => 'list', '--foo' => true), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception2.txt', $this->normalize($tester->getDisplay()), '->renderException() renders the command synopsis when an exception occurs in the context of a command');

        $application->add(new \Foo3Command);
        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'foo3:bar'), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception3.txt', $this->normalize($tester->getDisplay()), '->renderException() renders a pretty exceptions with previous exceptions');

    }

    public function testRun()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->add($command = new \Foo1Command());
        $_SERVER['argv'] = array('cli.php', 'foo:bar1');

        ob_start();
        $application->run();
        ob_end_clean();

        $this->assertEquals('Symfony\Component\Console\Input\ArgvInput', get_class($command->input), '->run() creates an ArgvInput by default if none is given');
        $this->assertEquals('Symfony\Component\Console\Output\ConsoleOutput', get_class($command->output), '->run() creates a ConsoleOutput by default if none is given');

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $tester = new ApplicationTester($application);
        $tester->run(array(), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run1.txt', $this->normalize($tester->getDisplay()), '->run() runs the list command if no argument is passed');

        $tester->run(array('--help' => true), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run2.txt', $this->normalize($tester->getDisplay()), '->run() runs the help command if --help is passed');

        $tester->run(array('-h' => true), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run2.txt', $this->normalize($tester->getDisplay()), '->run() runs the help command if -h is passed');

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'list', '--help' => true), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run3.txt', $this->normalize($tester->getDisplay()), '->run() displays the help if --help is passed');

        $tester->run(array('command' => 'list', '-h' => true), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run3.txt', $this->normalize($tester->getDisplay()), '->run() displays the help if -h is passed');

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $tester = new ApplicationTester($application);
        $tester->run(array('--ansi' => true));
        $this->assertTrue($tester->getOutput()->isDecorated(), '->run() forces color output if --ansi is passed');

        $tester->run(array('--no-ansi' => true));
        $this->assertFalse($tester->getOutput()->isDecorated(), '->run() forces color output to be disabled if --no-ansi is passed');

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $tester = new ApplicationTester($application);
        $tester->run(array('--version' => true), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run4.txt', $this->normalize($tester->getDisplay()), '->run() displays the program version if --version is passed');

        $tester->run(array('-V' => true), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run4.txt', $this->normalize($tester->getDisplay()), '->run() displays the program version if -v is passed');

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'list', '--quiet' => true));
        $this->assertEquals('', $this->normalize($tester->getDisplay()), '->run() removes all output if --quiet is passed');

        $tester->run(array('command' => 'list', '-q' => true));
        $this->assertEquals('', $this->normalize($tester->getDisplay()), '->run() removes all output if -q is passed');

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'list', '--verbose' => true));
        $this->assertEquals(Output::VERBOSITY_VERBOSE, $tester->getOutput()->getVerbosity(), '->run() sets the output to verbose is --verbose is passed');

        $tester->run(array('command' => 'list', '-v' => true));
        $this->assertEquals(Output::VERBOSITY_VERBOSE, $tester->getOutput()->getVerbosity(), '->run() sets the output to verbose is -v is passed');

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->add(new \FooCommand());
        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'foo:bar', '--no-interaction' => true), array('decorated' => false));
        $this->assertEquals("called\n", $this->normalize($tester->getDisplay()), '->run() does not called interact() if --no-interaction is passed');

        $tester->run(array('command' => 'foo:bar', '-n' => true), array('decorated' => false));
        $this->assertEquals("called\n", $this->normalize($tester->getDisplay()), '->run() does not called interact() if -n is passed');
    }
}
