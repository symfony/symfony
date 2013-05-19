<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Tester\ApplicationTester;

class ApplicationTest extends \PHPUnit_Framework_TestCase
{
    protected static $fixturesPath;

    public static function setUpBeforeClass()
    {
        self::$fixturesPath = realpath(__DIR__.'/Fixtures/');
        require_once self::$fixturesPath.'/FooCommand.php';
        require_once self::$fixturesPath.'/Foo1Command.php';
        require_once self::$fixturesPath.'/Foo2Command.php';
        require_once self::$fixturesPath.'/Foo3Command.php';
        require_once self::$fixturesPath.'/Foo4Command.php';
    }

    protected function normalizeLineBreaks($text)
    {
        return str_replace(PHP_EOL, "\n", $text);
    }

    /**
     * Replaces the dynamic placeholders of the command help text with a static version.
     * The placeholder %command.full_name% includes the script path that is not predictable
     * and can not be tested against.
     */
    protected function ensureStaticCommandHelp(Application $application)
    {
        foreach ($application->all() as $command) {
            $command->setHelp(str_replace('%command.full_name%', 'app/console %command.name%', $command->getHelp()));
        }
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
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_gethelp.txt', $this->normalizeLineBreaks($application->getHelp()), '->setHelp() returns a help message');
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
            $this->assertRegExp('/Command "f" is not defined./', $e->getMessage(), '->find() throws an \InvalidArgumentException if the abbreviation is ambiguous for a namespace');
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

    public function testFindAlternativeExceptionMessage()
    {
        $application = new Application();
        $application->add(new \FooCommand());

        // Command + singular
        try {
            $application->find('foo:baR');
            $this->fail('->find() throws an \InvalidArgumentException if command does not exist, with one alternative');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->find() throws an \InvalidArgumentException if command does not exist, with one alternative');
            $this->assertRegExp('/Did you mean this/', $e->getMessage(), '->find() throws an \InvalidArgumentException if command does not exist, with one alternative');
        }

        // Namespace + singular
        try {
            $application->find('foO:bar');
            $this->fail('->find() throws an \InvalidArgumentException if command does not exist, with one alternative');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->find() throws an \InvalidArgumentException if command does not exist, with one alternative');
            $this->assertRegExp('/Did you mean this/', $e->getMessage(), '->find() throws an \InvalidArgumentException if command does not exist, with one alternative');
        }

        $application->add(new \Foo1Command());
        $application->add(new \Foo2Command());

        // Command + plural
        try {
            $application->find('foo:baR');
            $this->fail('->find() throws an \InvalidArgumentException if command does not exist, with alternatives');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->find() throws an \InvalidArgumentException if command does not exist, with alternatives');
            $this->assertRegExp('/Did you mean one of these/', $e->getMessage(), '->find() throws an \InvalidArgumentException if command does not exist, with alternatives');
        }

        // Namespace + plural
        try {
            $application->find('foo2:bar');
            $this->fail('->find() throws an \InvalidArgumentException if command does not exist, with alternatives');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->find() throws an \InvalidArgumentException if command does not exist, with alternatives');
            $this->assertRegExp('/Did you mean one of these/', $e->getMessage(), '->find() throws an \InvalidArgumentException if command does not exist, with alternatives');
        }

        $application->add(new \Foo3Command());
        $application->add(new \Foo4Command());

        // Subnamespace + plural
        try {
            $a = $application->find('foo3:');
            $this->fail('->find() should throw an \InvalidArgumentException if a command is ambiguous because of a subnamespace, with alternatives');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e);
            $this->assertRegExp('/foo3:bar/', $e->getMessage());
            $this->assertRegExp('/foo3:bar:toh/', $e->getMessage());
        }
    }

    public function testFindAlternativeCommands()
    {
        $application = new Application();

        $application->add(new \FooCommand());
        $application->add(new \Foo1Command());
        $application->add(new \Foo2Command());

        try {
            $application->find($commandName = 'Unknown command');
            $this->fail('->find() throws an \InvalidArgumentException if command does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->find() throws an \InvalidArgumentException if command does not exist');
            $this->assertEquals(sprintf('Command "%s" is not defined.', $commandName), $e->getMessage(), '->find() throws an \InvalidArgumentException if command does not exist, without alternatives');
        }

        try {
            $application->find($commandName = 'foo');
            $this->fail('->find() throws an \InvalidArgumentException if command does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->find() throws an \InvalidArgumentException if command does not exist');
            $this->assertRegExp(sprintf('/Command "%s" is not defined./', $commandName), $e->getMessage(), '->find() throws an \InvalidArgumentException if command does not exist, with alternatives');
            $this->assertRegExp('/foo:bar/', $e->getMessage(), '->find() throws an \InvalidArgumentException if command does not exist, with alternative : "foo:bar"');
            $this->assertRegExp('/foo1:bar/', $e->getMessage(), '->find() throws an \InvalidArgumentException if command does not exist, with alternative : "foo1:bar"');
            $this->assertRegExp('/foo:bar1/', $e->getMessage(), '->find() throws an \InvalidArgumentException if command does not exist, with alternative : "foo:bar1"');
        }

        // Test if "foo1" command throw an "\InvalidArgumentException" and does not contain
        // "foo:bar" as alternative because "foo1" is too far from "foo:bar"
        try {
            $application->find($commandName = 'foo1');
            $this->fail('->find() throws an \InvalidArgumentException if command does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->find() throws an \InvalidArgumentException if command does not exist');
            $this->assertRegExp(sprintf('/Command "%s" is not defined./', $commandName), $e->getMessage(), '->find() throws an \InvalidArgumentException if command does not exist, with alternatives');
            $this->assertFalse(strpos($e->getMessage(), 'foo:bar'), '->find() throws an \InvalidArgumentException if command does not exist, without "foo:bar" alternative');
        }
    }

    public function testFindAlternativeNamespace()
    {
        $application = new Application();

        $application->add(new \FooCommand());
        $application->add(new \Foo1Command());
        $application->add(new \Foo2Command());
        $application->add(new \foo3Command());

        try {
            $application->find('Unknown-namespace:Unknown-command');
            $this->fail('->find() throws an \InvalidArgumentException if namespace does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->find() throws an \InvalidArgumentException if namespace does not exist');
            $this->assertEquals('There are no commands defined in the "Unknown-namespace" namespace.', $e->getMessage(), '->find() throws an \InvalidArgumentException if namespace does not exist, without alternatives');
        }

        try {
            $application->find('foo2:command');
            $this->fail('->find() throws an \InvalidArgumentException if namespace does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->find() throws an \InvalidArgumentException if namespace does not exist');
            $this->assertRegExp('/There are no commands defined in the "foo2" namespace./', $e->getMessage(), '->find() throws an \InvalidArgumentException if namespace does not exist, with alternative');
            $this->assertRegExp('/foo/', $e->getMessage(), '->find() throws an \InvalidArgumentException if namespace does not exist, with alternative : "foo"');
            $this->assertRegExp('/foo1/', $e->getMessage(), '->find() throws an \InvalidArgumentException if namespace does not exist, with alternative : "foo1"');
            $this->assertRegExp('/foo3/', $e->getMessage(), '->find() throws an \InvalidArgumentException if namespace does not exist, with alternative : "foo3"');
        }
    }

    public function testFindNamespaceDoesNotFailOnDeepSimilarNamespaces()
    {
        $application = $this->getMock('Symfony\Component\Console\Application', array('getNamespaces'));
        $application->expects($this->once())
            ->method('getNamespaces')
            ->will($this->returnValue(array('foo:sublong', 'bar:sub')));

        $this->assertEquals('foo:sublong', $application->findNamespace('f:sub'));
    }

    public function testSetCatchExceptions()
    {
        $application = $this->getMock('Symfony\Component\Console\Application', array('getTerminalWidth'));
        $application->setAutoExit(false);
        $application->expects($this->any())
            ->method('getTerminalWidth')
            ->will($this->returnValue(120));
        $tester = new ApplicationTester($application);

        $application->setCatchExceptions(true);
        $tester->run(array('command' => 'foo'), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception1.txt', $this->normalizeLineBreaks($tester->getDisplay()), '->setCatchExceptions() sets the catch exception flag');

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
        $this->ensureStaticCommandHelp($application);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_astext1.txt', $this->normalizeLineBreaks($application->asText()), '->asText() returns a text representation of the application');
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_astext2.txt', $this->normalizeLineBreaks($application->asText('foo')), '->asText() returns a text representation of the application');
    }

    public function testAsXml()
    {
        $application = new Application();
        $application->add(new \FooCommand);
        $this->ensureStaticCommandHelp($application);
        $this->assertXmlStringEqualsXmlFile(self::$fixturesPath.'/application_asxml1.txt', $application->asXml(), '->asXml() returns an XML representation of the application');
        $this->assertXmlStringEqualsXmlFile(self::$fixturesPath.'/application_asxml2.txt', $application->asXml('foo'), '->asXml() returns an XML representation of the application');
    }

    public function testRenderException()
    {
        $application = $this->getMock('Symfony\Component\Console\Application', array('getTerminalWidth'));
        $application->setAutoExit(false);
        $application->expects($this->any())
            ->method('getTerminalWidth')
            ->will($this->returnValue(120));
        $tester = new ApplicationTester($application);

        $tester->run(array('command' => 'foo'), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception1.txt', $this->normalizeLineBreaks($tester->getDisplay()), '->renderException() renders a pretty exception');

        $tester->run(array('command' => 'foo'), array('decorated' => false, 'verbosity' => Output::VERBOSITY_VERBOSE));
        $this->assertContains('Exception trace', $tester->getDisplay(), '->renderException() renders a pretty exception with a stack trace when verbosity is verbose');

        $tester->run(array('command' => 'list', '--foo' => true), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception2.txt', $this->normalizeLineBreaks($tester->getDisplay()), '->renderException() renders the command synopsis when an exception occurs in the context of a command');

        $application->add(new \Foo3Command);
        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'foo3:bar'), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception3.txt', $this->normalizeLineBreaks($tester->getDisplay()), '->renderException() renders a pretty exceptions with previous exceptions');

        $application = $this->getMock('Symfony\Component\Console\Application', array('getTerminalWidth'));
        $application->setAutoExit(false);
        $application->expects($this->any())
            ->method('getTerminalWidth')
            ->will($this->returnValue(32));
        $tester = new ApplicationTester($application);

        $tester->run(array('command' => 'foo'), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception4.txt', $this->normalizeLineBreaks($tester->getDisplay()), '->renderException() wraps messages when they are bigger than the terminal');
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

        $this->assertSame('Symfony\Component\Console\Input\ArgvInput', get_class($command->input), '->run() creates an ArgvInput by default if none is given');
        $this->assertSame('Symfony\Component\Console\Output\ConsoleOutput', get_class($command->output), '->run() creates a ConsoleOutput by default if none is given');

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $this->ensureStaticCommandHelp($application);
        $tester = new ApplicationTester($application);

        $tester->run(array(), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run1.txt', $this->normalizeLineBreaks($tester->getDisplay()), '->run() runs the list command if no argument is passed');

        $tester->run(array('--help' => true), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run2.txt', $this->normalizeLineBreaks($tester->getDisplay()), '->run() runs the help command if --help is passed');

        $tester->run(array('-h' => true), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run2.txt', $this->normalizeLineBreaks($tester->getDisplay()), '->run() runs the help command if -h is passed');

        $tester->run(array('command' => 'list', '--help' => true), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run3.txt', $this->normalizeLineBreaks($tester->getDisplay()), '->run() displays the help if --help is passed');

        $tester->run(array('command' => 'list', '-h' => true), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run3.txt', $this->normalizeLineBreaks($tester->getDisplay()), '->run() displays the help if -h is passed');

        $tester->run(array('--ansi' => true));
        $this->assertTrue($tester->getOutput()->isDecorated(), '->run() forces color output if --ansi is passed');

        $tester->run(array('--no-ansi' => true));
        $this->assertFalse($tester->getOutput()->isDecorated(), '->run() forces color output to be disabled if --no-ansi is passed');

        $tester->run(array('--version' => true), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run4.txt', $this->normalizeLineBreaks($tester->getDisplay()), '->run() displays the program version if --version is passed');

        $tester->run(array('-V' => true), array('decorated' => false));
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run4.txt', $this->normalizeLineBreaks($tester->getDisplay()), '->run() displays the program version if -v is passed');

        $tester->run(array('command' => 'list', '--quiet' => true));
        $this->assertSame('', $tester->getDisplay(), '->run() removes all output if --quiet is passed');

        $tester->run(array('command' => 'list', '-q' => true));
        $this->assertSame('', $tester->getDisplay(), '->run() removes all output if -q is passed');

        $tester->run(array('command' => 'list', '--verbose' => true));
        $this->assertSame(Output::VERBOSITY_VERBOSE, $tester->getOutput()->getVerbosity(), '->run() sets the output to verbose if --verbose is passed');

        $tester->run(array('command' => 'list', '-v' => true));
        $this->assertSame(Output::VERBOSITY_VERBOSE, $tester->getOutput()->getVerbosity(), '->run() sets the output to verbose if -v is passed');

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->add(new \FooCommand());
        $tester = new ApplicationTester($application);

        $tester->run(array('command' => 'foo:bar', '--no-interaction' => true), array('decorated' => false));
        $this->assertSame('called'.PHP_EOL, $tester->getDisplay(), '->run() does not call interact() if --no-interaction is passed');

        $tester->run(array('command' => 'foo:bar', '-n' => true), array('decorated' => false));
        $this->assertSame('called'.PHP_EOL, $tester->getDisplay(), '->run() does not call interact() if -n is passed');
    }

    public function testRunReturnsIntegerExitCode()
    {
        $exception = new \Exception('', 4);

        $application = $this->getMock('Symfony\Component\Console\Application', array('doRun'));
        $application->setAutoExit(false);
        $application->expects($this->once())
             ->method('doRun')
             ->will($this->throwException($exception));

        $exitCode = $application->run(new ArrayInput(array()), new NullOutput());

        $this->assertSame(4, $exitCode, '->run() returns integer exit code extracted from raised exception');
    }

    /**
     * @expectedException \LogicException
     * @dataProvider getAddingAlreadySetDefinitionElementData
     */
    public function testAddingAlreadySetDefinitionElementData($def)
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application
            ->register('foo')
            ->setDefinition(array($def))
            ->setCode(function (InputInterface $input, OutputInterface $output) {})
        ;

        $input = new ArrayInput(array('command' => 'foo'));
        $output = new NullOutput();
        $application->run($input, $output);
    }

    public function getAddingAlreadySetDefinitionElementData()
    {
        return array(
            array(new InputArgument('command', InputArgument::REQUIRED)),
            array(new InputOption('quiet', '', InputOption::VALUE_NONE)),
            array(new InputOption('query', 'q', InputOption::VALUE_NONE)),
        );
    }
}
