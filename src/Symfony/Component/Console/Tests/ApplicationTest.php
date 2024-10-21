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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\NamespaceNotFoundException;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\SignalRegistry\SignalRegistry;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tests\Fixtures\MockableAppliationWithTerminalWidth;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Process\Process;

class ApplicationTest extends TestCase
{
    protected static $fixturesPath;

    private $colSize;

    protected function setUp(): void
    {
        $this->colSize = getenv('COLUMNS');
    }

    protected function tearDown(): void
    {
        putenv($this->colSize ? 'COLUMNS='.$this->colSize : 'COLUMNS');
        putenv('SHELL_VERBOSITY');
        unset($_ENV['SHELL_VERBOSITY']);
        unset($_SERVER['SHELL_VERBOSITY']);

        if (\function_exists('pcntl_signal')) {
            // We reset all signals to their default value to avoid side effects
            for ($i = 1; $i <= 15; ++$i) {
                if (9 === $i) {
                    continue;
                }
                pcntl_signal($i, SIG_DFL);
            }
        }
    }

    public static function setUpBeforeClass(): void
    {
        self::$fixturesPath = realpath(__DIR__.'/Fixtures/');
        require_once self::$fixturesPath.'/FooCommand.php';
        require_once self::$fixturesPath.'/FooOptCommand.php';
        require_once self::$fixturesPath.'/Foo1Command.php';
        require_once self::$fixturesPath.'/Foo2Command.php';
        require_once self::$fixturesPath.'/Foo3Command.php';
        require_once self::$fixturesPath.'/Foo4Command.php';
        require_once self::$fixturesPath.'/Foo5Command.php';
        require_once self::$fixturesPath.'/FooSameCaseUppercaseCommand.php';
        require_once self::$fixturesPath.'/FooSameCaseLowercaseCommand.php';
        require_once self::$fixturesPath.'/FoobarCommand.php';
        require_once self::$fixturesPath.'/BarBucCommand.php';
        require_once self::$fixturesPath.'/FooSubnamespaced1Command.php';
        require_once self::$fixturesPath.'/FooSubnamespaced2Command.php';
        require_once self::$fixturesPath.'/FooWithoutAliasCommand.php';
        require_once self::$fixturesPath.'/TestAmbiguousCommandRegistering.php';
        require_once self::$fixturesPath.'/TestAmbiguousCommandRegistering2.php';
        require_once self::$fixturesPath.'/FooHiddenCommand.php';
        require_once self::$fixturesPath.'/BarHiddenCommand.php';
    }

    protected function normalizeLineBreaks($text)
    {
        return str_replace(\PHP_EOL, "\n", $text);
    }

    /**
     * Replaces the dynamic placeholders of the command help text with a static version.
     * The placeholder %command.full_name% includes the script path that is not predictable
     * and cannot be tested against.
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
        $this->assertEquals('bar', $application->getVersion(), '__construct() takes the application version as its second argument');
        $this->assertEquals(['help', 'list', '_complete', 'completion'], array_keys($application->all()), '__construct() registered the help and list commands by default');
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
        $this->assertEquals('foo <info>bar</info>', $application->getLongVersion(), '->getLongVersion() returns the long version of the application');
    }

    public function testHelp()
    {
        $application = new Application();
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_gethelp.txt', $this->normalizeLineBreaks($application->getHelp()), '->getHelp() returns a help message');
    }

    public function testAll()
    {
        $application = new Application();
        $commands = $application->all();
        $this->assertInstanceOf(HelpCommand::class, $commands['help'], '->all() returns the registered commands');

        $application->add(new \FooCommand());
        $commands = $application->all('foo');
        $this->assertCount(1, $commands, '->all() takes a namespace as its first argument');
    }

    public function testAllWithCommandLoader()
    {
        $application = new Application();
        $commands = $application->all();
        $this->assertInstanceOf(HelpCommand::class, $commands['help'], '->all() returns the registered commands');

        $application->add(new \FooCommand());
        $commands = $application->all('foo');
        $this->assertCount(1, $commands, '->all() takes a namespace as its first argument');

        $application->setCommandLoader(new FactoryCommandLoader([
            'foo:bar1' => function () { return new \Foo1Command(); },
        ]));
        $commands = $application->all('foo');
        $this->assertCount(2, $commands, '->all() takes a namespace as its first argument');
        $this->assertInstanceOf(\FooCommand::class, $commands['foo:bar'], '->all() returns the registered commands');
        $this->assertInstanceOf(\Foo1Command::class, $commands['foo:bar1'], '->all() returns the registered commands');
    }

    public function testRegister()
    {
        $application = new Application();
        $command = $application->register('foo');
        $this->assertEquals('foo', $command->getName(), '->register() registers a new command');
    }

    public function testRegisterAmbiguous()
    {
        $code = function (InputInterface $input, OutputInterface $output) {
            $output->writeln('It works!');
        };

        $application = new Application();
        $application->setAutoExit(false);
        $application
            ->register('test-foo')
            ->setAliases(['test'])
            ->setCode($code);

        $application
            ->register('test-bar')
            ->setCode($code);

        $tester = new ApplicationTester($application);
        $tester->run(['test']);
        $this->assertStringContainsString('It works!', $tester->getDisplay(true));
    }

    public function testAdd()
    {
        $application = new Application();
        $application->add($foo = new \FooCommand());
        $commands = $application->all();
        $this->assertEquals($foo, $commands['foo:bar'], '->add() registers a command');

        $application = new Application();
        $application->addCommands([$foo = new \FooCommand(), $foo1 = new \Foo1Command()]);
        $commands = $application->all();
        $this->assertEquals([$foo, $foo1], [$commands['foo:bar'], $commands['foo:bar1']], '->addCommands() registers an array of commands');
    }

    public function testAddCommandWithEmptyConstructor()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Command class "Foo5Command" is not correctly initialized. You probably forgot to call the parent constructor.');
        $application = new Application();
        $application->add(new \Foo5Command());
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

        $application = new Application();
        $application->add($foo = new \FooCommand());
        // simulate --help
        $r = new \ReflectionObject($application);
        $p = $r->getProperty('wantHelps');
        $p->setAccessible(true);
        $p->setValue($application, true);
        $command = $application->get('foo:bar');
        $this->assertInstanceOf(HelpCommand::class, $command, '->get() returns the help command if --help is provided as the input');
    }

    public function testHasGetWithCommandLoader()
    {
        $application = new Application();
        $this->assertTrue($application->has('list'), '->has() returns true if a named command is registered');
        $this->assertFalse($application->has('afoobar'), '->has() returns false if a named command is not registered');

        $application->add($foo = new \FooCommand());
        $this->assertTrue($application->has('afoobar'), '->has() returns true if an alias is registered');
        $this->assertEquals($foo, $application->get('foo:bar'), '->get() returns a command by name');
        $this->assertEquals($foo, $application->get('afoobar'), '->get() returns a command by alias');

        $application->setCommandLoader(new FactoryCommandLoader([
            'foo:bar1' => function () { return new \Foo1Command(); },
        ]));

        $this->assertTrue($application->has('afoobar'), '->has() returns true if an instance is registered for an alias even with command loader');
        $this->assertEquals($foo, $application->get('foo:bar'), '->get() returns an instance by name even with command loader');
        $this->assertEquals($foo, $application->get('afoobar'), '->get() returns an instance by alias even with command loader');
        $this->assertTrue($application->has('foo:bar1'), '->has() returns true for commands registered in the loader');
        $this->assertInstanceOf(\Foo1Command::class, $foo1 = $application->get('foo:bar1'), '->get() returns a command by name from the command loader');
        $this->assertTrue($application->has('afoobar1'), '->has() returns true for commands registered in the loader');
        $this->assertEquals($foo1, $application->get('afoobar1'), '->get() returns a command by name from the command loader');
    }

    public function testSilentHelp()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $tester = new ApplicationTester($application);
        $tester->run(['-h' => true, '-q' => true], ['decorated' => false]);

        $this->assertEmpty($tester->getDisplay(true));
    }

    public function testGetInvalidCommand()
    {
        $this->expectException(CommandNotFoundException::class);
        $this->expectExceptionMessage('The command "foofoo" does not exist.');
        $application = new Application();
        $application->get('foofoo');
    }

    public function testGetNamespaces()
    {
        $application = new Application();
        $application->add(new \FooCommand());
        $application->add(new \Foo1Command());
        $this->assertEquals(['foo'], $application->getNamespaces(), '->getNamespaces() returns an array of unique used namespaces');
    }

    public function testFindNamespace()
    {
        $application = new Application();
        $application->add(new \FooCommand());
        $this->assertEquals('foo', $application->findNamespace('foo'), '->findNamespace() returns the given namespace if it exists');
        $this->assertEquals('foo', $application->findNamespace('f'), '->findNamespace() finds a namespace given an abbreviation');
        $application->add(new \Foo2Command());
        $this->assertEquals('foo', $application->findNamespace('foo'), '->findNamespace() returns the given namespace if it exists');
    }

    public function testFindNamespaceWithSubnamespaces()
    {
        $application = new Application();
        $application->add(new \FooSubnamespaced1Command());
        $application->add(new \FooSubnamespaced2Command());
        $this->assertEquals('foo', $application->findNamespace('foo'), '->findNamespace() returns commands even if the commands are only contained in subnamespaces');
    }

    public function testFindAmbiguousNamespace()
    {
        $application = new Application();
        $application->add(new \BarBucCommand());
        $application->add(new \FooCommand());
        $application->add(new \Foo2Command());

        $expectedMsg = "The namespace \"f\" is ambiguous.\nDid you mean one of these?\n    foo\n    foo1";

        $this->expectException(NamespaceNotFoundException::class);
        $this->expectExceptionMessage($expectedMsg);

        $application->findNamespace('f');
    }

    public function testFindNonAmbiguous()
    {
        $application = new Application();
        $application->add(new \TestAmbiguousCommandRegistering());
        $application->add(new \TestAmbiguousCommandRegistering2());
        $this->assertEquals('test-ambiguous', $application->find('test')->getName());
    }

    public function testFindInvalidNamespace()
    {
        $this->expectException(NamespaceNotFoundException::class);
        $this->expectExceptionMessage('There are no commands defined in the "bar" namespace.');
        $application = new Application();
        $application->findNamespace('bar');
    }

    public function testFindUniqueNameButNamespaceName()
    {
        $this->expectException(CommandNotFoundException::class);
        $this->expectExceptionMessage('Command "foo1" is not defined');
        $application = new Application();
        $application->add(new \FooCommand());
        $application->add(new \Foo1Command());
        $application->add(new \Foo2Command());

        $application->find($commandName = 'foo1');
    }

    public function testFind()
    {
        $application = new Application();
        $application->add(new \FooCommand());

        $this->assertInstanceOf(\FooCommand::class, $application->find('foo:bar'), '->find() returns a command if its name exists');
        $this->assertInstanceOf(HelpCommand::class, $application->find('h'), '->find() returns a command if its name exists');
        $this->assertInstanceOf(\FooCommand::class, $application->find('f:bar'), '->find() returns a command if the abbreviation for the namespace exists');
        $this->assertInstanceOf(\FooCommand::class, $application->find('f:b'), '->find() returns a command if the abbreviation for the namespace and the command name exist');
        $this->assertInstanceOf(\FooCommand::class, $application->find('a'), '->find() returns a command if the abbreviation exists for an alias');
    }

    public function testFindCaseSensitiveFirst()
    {
        $application = new Application();
        $application->add(new \FooSameCaseUppercaseCommand());
        $application->add(new \FooSameCaseLowercaseCommand());

        $this->assertInstanceOf(\FooSameCaseUppercaseCommand::class, $application->find('f:B'), '->find() returns a command if the abbreviation is the correct case');
        $this->assertInstanceOf(\FooSameCaseUppercaseCommand::class, $application->find('f:BAR'), '->find() returns a command if the abbreviation is the correct case');
        $this->assertInstanceOf(\FooSameCaseLowercaseCommand::class, $application->find('f:b'), '->find() returns a command if the abbreviation is the correct case');
        $this->assertInstanceOf(\FooSameCaseLowercaseCommand::class, $application->find('f:bar'), '->find() returns a command if the abbreviation is the correct case');
    }

    public function testFindCaseInsensitiveAsFallback()
    {
        $application = new Application();
        $application->add(new \FooSameCaseLowercaseCommand());

        $this->assertInstanceOf(\FooSameCaseLowercaseCommand::class, $application->find('f:b'), '->find() returns a command if the abbreviation is the correct case');
        $this->assertInstanceOf(\FooSameCaseLowercaseCommand::class, $application->find('f:B'), '->find() will fallback to case insensitivity');
        $this->assertInstanceOf(\FooSameCaseLowercaseCommand::class, $application->find('FoO:BaR'), '->find() will fallback to case insensitivity');
    }

    public function testFindCaseInsensitiveSuggestions()
    {
        $this->expectException(CommandNotFoundException::class);
        $this->expectExceptionMessage('Command "FoO:BaR" is ambiguous');
        $application = new Application();
        $application->add(new \FooSameCaseLowercaseCommand());
        $application->add(new \FooSameCaseUppercaseCommand());

        $this->assertInstanceOf(\FooSameCaseLowercaseCommand::class, $application->find('FoO:BaR'), '->find() will find two suggestions with case insensitivity');
    }

    public function testFindWithCommandLoader()
    {
        $application = new Application();
        $application->setCommandLoader(new FactoryCommandLoader([
            'foo:bar' => $f = function () { return new \FooCommand(); },
        ]));

        $this->assertInstanceOf(\FooCommand::class, $application->find('foo:bar'), '->find() returns a command if its name exists');
        $this->assertInstanceOf(HelpCommand::class, $application->find('h'), '->find() returns a command if its name exists');
        $this->assertInstanceOf(\FooCommand::class, $application->find('f:bar'), '->find() returns a command if the abbreviation for the namespace exists');
        $this->assertInstanceOf(\FooCommand::class, $application->find('f:b'), '->find() returns a command if the abbreviation for the namespace and the command name exist');
        $this->assertInstanceOf(\FooCommand::class, $application->find('a'), '->find() returns a command if the abbreviation exists for an alias');
    }

    /**
     * @dataProvider provideAmbiguousAbbreviations
     */
    public function testFindWithAmbiguousAbbreviations($abbreviation, $expectedExceptionMessage)
    {
        putenv('COLUMNS=120');
        $this->expectException(CommandNotFoundException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $application = new Application();
        $application->add(new \FooCommand());
        $application->add(new \Foo1Command());
        $application->add(new \Foo2Command());

        $application->find($abbreviation);
    }

    public static function provideAmbiguousAbbreviations()
    {
        return [
            ['f', 'Command "f" is not defined.'],
            [
                'a',
                "Command \"a\" is ambiguous.\nDid you mean one of these?\n".
                "    afoobar  The foo:bar command\n".
                "    afoobar1 The foo:bar1 command\n".
                '    afoobar2 The foo1:bar command',
            ],
            [
                'foo:b',
                "Command \"foo:b\" is ambiguous.\nDid you mean one of these?\n".
                "    foo:bar  The foo:bar command\n".
                "    foo:bar1 The foo:bar1 command\n".
                '    foo1:bar The foo1:bar command',
            ],
        ];
    }

    public function testFindWithAmbiguousAbbreviationsFindsCommandIfAlternativesAreHidden()
    {
        $application = new Application();

        $application->add(new \FooCommand());
        $application->add(new \FooHiddenCommand());

        $this->assertInstanceOf(\FooCommand::class, $application->find('foo:'));
    }

    public function testFindCommandEqualNamespace()
    {
        $application = new Application();
        $application->add(new \Foo3Command());
        $application->add(new \Foo4Command());

        $this->assertInstanceOf(\Foo3Command::class, $application->find('foo3:bar'), '->find() returns the good command even if a namespace has same name');
        $this->assertInstanceOf(\Foo4Command::class, $application->find('foo3:bar:toh'), '->find() returns a command even if its namespace equals another command name');
    }

    public function testFindCommandWithAmbiguousNamespacesButUniqueName()
    {
        $application = new Application();
        $application->add(new \FooCommand());
        $application->add(new \FoobarCommand());

        $this->assertInstanceOf(\FoobarCommand::class, $application->find('f:f'));
    }

    public function testFindCommandWithMissingNamespace()
    {
        $application = new Application();
        $application->add(new \Foo4Command());

        $this->assertInstanceOf(\Foo4Command::class, $application->find('f::t'));
    }

    /**
     * @dataProvider provideInvalidCommandNamesSingle
     */
    public function testFindAlternativeExceptionMessageSingle($name)
    {
        $this->expectException(CommandNotFoundException::class);
        $this->expectExceptionMessage('Did you mean this');
        $application = new Application();
        $application->add(new \Foo3Command());
        $application->find($name);
    }

    public function testDontRunAlternativeNamespaceName()
    {
        $application = new Application();
        $application->add(new \Foo1Command());
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'foos:bar1'], ['decorated' => false]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application.dont_run_alternative_namespace_name.txt', $tester->getDisplay(true));
    }

    public function testCanRunAlternativeCommandName()
    {
        $application = new Application();
        $application->add(new \FooWithoutAliasCommand());
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);
        $tester->setInputs(['y']);
        $tester->run(['command' => 'foos'], ['decorated' => false]);
        $display = trim($tester->getDisplay(true));
        $this->assertStringContainsString('Command "foos" is not defined', $display);
        $this->assertStringContainsString('Do you want to run "foo" instead?  (yes/no) [no]:', $display);
        $this->assertStringContainsString('called', $display);
    }

    public function testDontRunAlternativeCommandName()
    {
        $application = new Application();
        $application->add(new \FooWithoutAliasCommand());
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);
        $tester->setInputs(['n']);
        $exitCode = $tester->run(['command' => 'foos'], ['decorated' => false]);
        $this->assertSame(1, $exitCode);
        $display = trim($tester->getDisplay(true));
        $this->assertStringContainsString('Command "foos" is not defined', $display);
        $this->assertStringContainsString('Do you want to run "foo" instead?  (yes/no) [no]:', $display);
    }

    public static function provideInvalidCommandNamesSingle()
    {
        return [
            ['foo3:barr'],
            ['fooo3:bar'],
        ];
    }

    public function testFindAlternativeExceptionMessageMultiple()
    {
        putenv('COLUMNS=120');
        $application = new Application();
        $application->add(new \FooCommand());
        $application->add(new \Foo1Command());
        $application->add(new \Foo2Command());

        // Command + plural
        try {
            $application->find('foo:baR');
            $this->fail('->find() throws a CommandNotFoundException if command does not exist, with alternatives');
        } catch (\Exception $e) {
            $this->assertInstanceOf(CommandNotFoundException::class, $e, '->find() throws a CommandNotFoundException if command does not exist, with alternatives');
            $this->assertMatchesRegularExpression('/Did you mean one of these/', $e->getMessage(), '->find() throws a CommandNotFoundException if command does not exist, with alternatives');
            $this->assertMatchesRegularExpression('/foo1:bar/', $e->getMessage());
            $this->assertMatchesRegularExpression('/foo:bar/', $e->getMessage());
        }

        // Namespace + plural
        try {
            $application->find('foo2:bar');
            $this->fail('->find() throws a CommandNotFoundException if command does not exist, with alternatives');
        } catch (\Exception $e) {
            $this->assertInstanceOf(CommandNotFoundException::class, $e, '->find() throws a CommandNotFoundException if command does not exist, with alternatives');
            $this->assertMatchesRegularExpression('/Did you mean one of these/', $e->getMessage(), '->find() throws a CommandNotFoundException if command does not exist, with alternatives');
            $this->assertMatchesRegularExpression('/foo1/', $e->getMessage());
        }

        $application->add(new \Foo3Command());
        $application->add(new \Foo4Command());

        // Subnamespace + plural
        try {
            $application->find('foo3:');
            $this->fail('->find() should throw an Symfony\Component\Console\Exception\CommandNotFoundException if a command is ambiguous because of a subnamespace, with alternatives');
        } catch (\Exception $e) {
            $this->assertInstanceOf(CommandNotFoundException::class, $e);
            $this->assertMatchesRegularExpression('/foo3:bar/', $e->getMessage());
            $this->assertMatchesRegularExpression('/foo3:bar:toh/', $e->getMessage());
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
            $this->fail('->find() throws a CommandNotFoundException if command does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf(CommandNotFoundException::class, $e, '->find() throws a CommandNotFoundException if command does not exist');
            $this->assertSame([], $e->getAlternatives());
            $this->assertEquals(sprintf('Command "%s" is not defined.', $commandName), $e->getMessage(), '->find() throws a CommandNotFoundException if command does not exist, without alternatives');
        }

        // Test if "bar1" command throw a "CommandNotFoundException" and does not contain
        // "foo:bar" as alternative because "bar1" is too far from "foo:bar"
        try {
            $application->find($commandName = 'bar1');
            $this->fail('->find() throws a CommandNotFoundException if command does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf(CommandNotFoundException::class, $e, '->find() throws a CommandNotFoundException if command does not exist');
            $this->assertSame(['afoobar1', 'foo:bar1'], $e->getAlternatives());
            $this->assertMatchesRegularExpression(sprintf('/Command "%s" is not defined./', $commandName), $e->getMessage(), '->find() throws a CommandNotFoundException if command does not exist, with alternatives');
            $this->assertMatchesRegularExpression('/afoobar1/', $e->getMessage(), '->find() throws a CommandNotFoundException if command does not exist, with alternative : "afoobar1"');
            $this->assertMatchesRegularExpression('/foo:bar1/', $e->getMessage(), '->find() throws a CommandNotFoundException if command does not exist, with alternative : "foo:bar1"');
            $this->assertDoesNotMatchRegularExpression('/foo:bar(?!1)/', $e->getMessage(), '->find() throws a CommandNotFoundException if command does not exist, without "foo:bar" alternative');
        }
    }

    public function testFindAlternativeCommandsWithAnAlias()
    {
        $fooCommand = new \FooCommand();
        $fooCommand->setAliases(['foo2']);

        $application = new Application();
        $application->setCommandLoader(new FactoryCommandLoader([
            'foo3' => static function () use ($fooCommand) { return $fooCommand; },
        ]));
        $application->add($fooCommand);

        $result = $application->find('foo');

        $this->assertSame($fooCommand, $result);
    }

    public function testFindAlternativeNamespace()
    {
        $application = new Application();

        $application->add(new \FooCommand());
        $application->add(new \Foo1Command());
        $application->add(new \Foo2Command());
        $application->add(new \Foo3Command());

        try {
            $application->find('Unknown-namespace:Unknown-command');
            $this->fail('->find() throws a CommandNotFoundException if namespace does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf(CommandNotFoundException::class, $e, '->find() throws a CommandNotFoundException if namespace does not exist');
            $this->assertSame([], $e->getAlternatives());
            $this->assertEquals('There are no commands defined in the "Unknown-namespace" namespace.', $e->getMessage(), '->find() throws a CommandNotFoundException if namespace does not exist, without alternatives');
        }

        try {
            $application->find('foo2:command');
            $this->fail('->find() throws a CommandNotFoundException if namespace does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf(NamespaceNotFoundException::class, $e, '->find() throws a NamespaceNotFoundException if namespace does not exist');
            $this->assertInstanceOf(CommandNotFoundException::class, $e, 'NamespaceNotFoundException extends from CommandNotFoundException');
            $this->assertCount(3, $e->getAlternatives());
            $this->assertContains('foo', $e->getAlternatives());
            $this->assertContains('foo1', $e->getAlternatives());
            $this->assertContains('foo3', $e->getAlternatives());
            $this->assertMatchesRegularExpression('/There are no commands defined in the "foo2" namespace./', $e->getMessage(), '->find() throws a CommandNotFoundException if namespace does not exist, with alternative');
            $this->assertMatchesRegularExpression('/foo/', $e->getMessage(), '->find() throws a CommandNotFoundException if namespace does not exist, with alternative : "foo"');
            $this->assertMatchesRegularExpression('/foo1/', $e->getMessage(), '->find() throws a CommandNotFoundException if namespace does not exist, with alternative : "foo1"');
            $this->assertMatchesRegularExpression('/foo3/', $e->getMessage(), '->find() throws a CommandNotFoundException if namespace does not exist, with alternative : "foo3"');
        }
    }

    public function testFindAlternativesOutput()
    {
        $application = new Application();

        $application->add(new \FooCommand());
        $application->add(new \Foo1Command());
        $application->add(new \Foo2Command());
        $application->add(new \Foo3Command());
        $application->add(new \FooHiddenCommand());

        $expectedAlternatives = [
            'afoobar',
            'afoobar1',
            'afoobar2',
            'foo1:bar',
            'foo3:bar',
            'foo:bar',
            'foo:bar1',
        ];

        try {
            $application->find('foo');
            $this->fail('->find() throws a CommandNotFoundException if command is not defined');
        } catch (\Exception $e) {
            $this->assertInstanceOf(CommandNotFoundException::class, $e, '->find() throws a CommandNotFoundException if command is not defined');
            $this->assertSame($expectedAlternatives, $e->getAlternatives());

            $this->assertMatchesRegularExpression('/Command "foo" is not defined\..*Did you mean one of these\?.*/Ums', $e->getMessage());
        }
    }

    public function testFindNamespaceDoesNotFailOnDeepSimilarNamespaces()
    {
        $application = $this->getMockBuilder(Application::class)->onlyMethods(['getNamespaces'])->getMock();
        $application->expects($this->once())
            ->method('getNamespaces')
            ->willReturn(['foo:sublong', 'bar:sub']);

        $this->assertEquals('foo:sublong', $application->findNamespace('f:sub'));
    }

    public function testFindWithDoubleColonInNameThrowsException()
    {
        $this->expectException(CommandNotFoundException::class);
        $this->expectExceptionMessage('Command "foo::bar" is not defined.');
        $application = new Application();
        $application->add(new \FooCommand());
        $application->add(new \Foo4Command());
        $application->find('foo::bar');
    }

    public function testFindHiddenWithExactName()
    {
        $application = new Application();
        $application->add(new \FooHiddenCommand());

        $this->assertInstanceOf(\FooHiddenCommand::class, $application->find('foo:hidden'));
        $this->assertInstanceOf(\FooHiddenCommand::class, $application->find('afoohidden'));
    }

    public function testFindAmbiguousCommandsIfAllAlternativesAreHidden()
    {
        $application = new Application();

        $application->add(new \FooCommand());
        $application->add(new \FooHiddenCommand());

        $this->assertInstanceOf(\FooCommand::class, $application->find('foo:'));
    }

    public function testSetCatchExceptions()
    {
        $application = new Application();
        $application->setAutoExit(false);
        putenv('COLUMNS=120');
        $tester = new ApplicationTester($application);

        $application->setCatchExceptions(true);
        $this->assertTrue($application->areExceptionsCaught());

        $tester->run(['command' => 'foo'], ['decorated' => false]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception1.txt', $tester->getDisplay(true), '->setCatchExceptions() sets the catch exception flag');

        $tester->run(['command' => 'foo'], ['decorated' => false, 'capture_stderr_separately' => true]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception1.txt', $tester->getErrorOutput(true), '->setCatchExceptions() sets the catch exception flag');
        $this->assertSame('', $tester->getDisplay(true));

        $application->setCatchExceptions(false);
        try {
            $tester->run(['command' => 'foo'], ['decorated' => false]);
            $this->fail('->setCatchExceptions() sets the catch exception flag');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\Exception::class, $e, '->setCatchExceptions() sets the catch exception flag');
            $this->assertEquals('Command "foo" is not defined.', $e->getMessage(), '->setCatchExceptions() sets the catch exception flag');
        }
    }

    public function testAutoExitSetting()
    {
        $application = new Application();
        $this->assertTrue($application->isAutoExitEnabled());

        $application->setAutoExit(false);
        $this->assertFalse($application->isAutoExitEnabled());
    }

    public function testRenderException()
    {
        $application = new Application();
        $application->setAutoExit(false);
        putenv('COLUMNS=120');
        $tester = new ApplicationTester($application);

        $tester->run(['command' => 'foo'], ['decorated' => false, 'capture_stderr_separately' => true]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception1.txt', $tester->getErrorOutput(true), '->renderException() renders a pretty exception');

        $tester->run(['command' => 'foo'], ['decorated' => false, 'verbosity' => Output::VERBOSITY_VERBOSE, 'capture_stderr_separately' => true]);
        $this->assertStringContainsString('Exception trace', $tester->getErrorOutput(), '->renderException() renders a pretty exception with a stack trace when verbosity is verbose');

        $tester->run(['command' => 'list', '--foo' => true], ['decorated' => false, 'capture_stderr_separately' => true]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception2.txt', $tester->getErrorOutput(true), '->renderException() renders the command synopsis when an exception occurs in the context of a command');

        $application->add(new \Foo3Command());
        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'foo3:bar'], ['decorated' => false, 'capture_stderr_separately' => true]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception3.txt', $tester->getErrorOutput(true), '->renderException() renders a pretty exceptions with previous exceptions');

        $tester->run(['command' => 'foo3:bar'], ['decorated' => false, 'verbosity' => Output::VERBOSITY_VERBOSE]);
        $this->assertMatchesRegularExpression('/\[Exception\]\s*First exception/', $tester->getDisplay(), '->renderException() renders a pretty exception without code exception when code exception is default and verbosity is verbose');
        $this->assertMatchesRegularExpression('/\[Exception\]\s*Second exception/', $tester->getDisplay(), '->renderException() renders a pretty exception without code exception when code exception is 0 and verbosity is verbose');
        $this->assertMatchesRegularExpression('/\[Exception \(404\)\]\s*Third exception/', $tester->getDisplay(), '->renderException() renders a pretty exception with code exception when code exception is 404 and verbosity is verbose');

        $tester->run(['command' => 'foo3:bar'], ['decorated' => true]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception3decorated.txt', $tester->getDisplay(true), '->renderException() renders a pretty exceptions with previous exceptions');

        $tester->run(['command' => 'foo3:bar'], ['decorated' => true, 'capture_stderr_separately' => true]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception3decorated.txt', $tester->getErrorOutput(true), '->renderException() renders a pretty exceptions with previous exceptions');

        $application = new Application();
        $application->setAutoExit(false);
        putenv('COLUMNS=32');
        $tester = new ApplicationTester($application);

        $tester->run(['command' => 'foo'], ['decorated' => false,  'capture_stderr_separately' => true]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_renderexception4.txt', $tester->getErrorOutput(true), '->renderException() wraps messages when they are bigger than the terminal');
        putenv('COLUMNS=120');
    }

    public function testRenderExceptionWithDoubleWidthCharacters()
    {
        $application = new Application();
        $application->setAutoExit(false);
        putenv('COLUMNS=120');
        $application->register('foo')->setCode(function () {
            throw new \Exception('エラーメッセージ');
        });
        $tester = new ApplicationTester($application);

        $tester->run(['command' => 'foo'], ['decorated' => false, 'capture_stderr_separately' => true]);
        $this->assertStringMatchesFormatFile(self::$fixturesPath.'/application_renderexception_doublewidth1.txt', $tester->getErrorOutput(true), '->renderException() renders a pretty exceptions with previous exceptions');

        $tester->run(['command' => 'foo'], ['decorated' => true, 'capture_stderr_separately' => true]);
        $this->assertStringMatchesFormatFile(self::$fixturesPath.'/application_renderexception_doublewidth1decorated.txt', $tester->getErrorOutput(true), '->renderException() renders a pretty exceptions with previous exceptions');

        $application = new Application();
        $application->setAutoExit(false);
        putenv('COLUMNS=32');
        $application->register('foo')->setCode(function () {
            throw new \Exception('コマンドの実行中にエラーが発生しました。');
        });
        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'foo'], ['decorated' => false, 'capture_stderr_separately' => true]);
        $this->assertStringMatchesFormatFile(self::$fixturesPath.'/application_renderexception_doublewidth2.txt', $tester->getErrorOutput(true), '->renderException() wraps messages when they are bigger than the terminal');
        putenv('COLUMNS=120');
    }

    public function testRenderExceptionEscapesLines()
    {
        $application = new Application();
        $application->setAutoExit(false);
        putenv('COLUMNS=22');
        $application->register('foo')->setCode(function () {
            throw new \Exception('dont break here <info>!</info>');
        });
        $tester = new ApplicationTester($application);

        $tester->run(['command' => 'foo'], ['decorated' => false]);
        $this->assertStringMatchesFormatFile(self::$fixturesPath.'/application_renderexception_escapeslines.txt', $tester->getDisplay(true), '->renderException() escapes lines containing formatting');
        putenv('COLUMNS=120');
    }

    public function testRenderExceptionLineBreaks()
    {
        $application = $this->getMockBuilder(MockableAppliationWithTerminalWidth::class)
            ->onlyMethods(['getTerminalWidth'])
            ->getMock();
        $application->setAutoExit(false);
        $application->expects($this->any())
            ->method('getTerminalWidth')
            ->willReturn(120);
        $application->register('foo')->setCode(function () {
            throw new \InvalidArgumentException("\n\nline 1 with extra spaces        \nline 2\n\nline 4\n");
        });
        $tester = new ApplicationTester($application);

        $tester->run(['command' => 'foo'], ['decorated' => false]);
        $this->assertStringMatchesFormatFile(self::$fixturesPath.'/application_renderexception_linebreaks.txt', $tester->getDisplay(true), '->renderException() keep multiple line breaks');
    }

    /**
     * @group transient-on-windows
     */
    public function testRenderAnonymousException()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->register('foo')->setCode(function () {
            throw new class('') extends \InvalidArgumentException { };
        });
        $tester = new ApplicationTester($application);

        $tester->run(['command' => 'foo'], ['decorated' => false]);
        $this->assertStringContainsString('[InvalidArgumentException@anonymous]', $tester->getDisplay(true));

        $application = new Application();
        $application->setAutoExit(false);
        $application->register('foo')->setCode(function () {
            throw new \InvalidArgumentException(sprintf('Dummy type "%s" is invalid.', \get_class(new class() { })));
        });
        $tester = new ApplicationTester($application);

        $tester->run(['command' => 'foo'], ['decorated' => false]);
        $this->assertStringContainsString('Dummy type "class@anonymous" is invalid.', $tester->getDisplay(true));
    }

    /**
     * @group transient-on-windows
     */
    public function testRenderExceptionStackTraceContainsRootException()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->register('foo')->setCode(function () {
            throw new class('') extends \InvalidArgumentException { };
        });
        $tester = new ApplicationTester($application);

        $tester->run(['command' => 'foo'], ['decorated' => false]);
        $this->assertStringContainsString('[InvalidArgumentException@anonymous]', $tester->getDisplay(true));

        $application = new Application();
        $application->setAutoExit(false);
        $application->register('foo')->setCode(function () {
            throw new \InvalidArgumentException(sprintf('Dummy type "%s" is invalid.', \get_class(new class() { })));
        });
        $tester = new ApplicationTester($application);

        $tester->run(['command' => 'foo'], ['decorated' => false]);
        $this->assertStringContainsString('Dummy type "class@anonymous" is invalid.', $tester->getDisplay(true));
    }

    public function testRenderExceptionEscapesLinesOfSynopsis()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application
            ->register('foo')
            ->setCode(function () {
                throw new \Exception('some exception');
            })
            ->addArgument('info')
        ;
        $tester = new ApplicationTester($application);

        $tester->run(['command' => 'foo'], ['decorated' => false]);
        $this->assertStringMatchesFormatFile(self::$fixturesPath.'/application_rendersynopsis_escapesline.txt', $tester->getDisplay(true), '->renderException() escapes lines containing formatting of synopsis');
    }

    public function testRun()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->add($command = new \Foo1Command());
        $_SERVER['argv'] = ['cli.php', 'foo:bar1'];

        ob_start();
        $application->run();
        ob_end_clean();

        $this->assertInstanceOf(ArgvInput::class, $command->input, '->run() creates an ArgvInput by default if none is given');
        $this->assertInstanceOf(ConsoleOutput::class, $command->output, '->run() creates a ConsoleOutput by default if none is given');

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $this->ensureStaticCommandHelp($application);
        $tester = new ApplicationTester($application);

        $tester->run([], ['decorated' => false]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run1.txt', $tester->getDisplay(true), '->run() runs the list command if no argument is passed');

        $tester->run(['--help' => true], ['decorated' => false]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run2.txt', $tester->getDisplay(true), '->run() runs the help command if --help is passed');

        $tester->run(['-h' => true], ['decorated' => false]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run2.txt', $tester->getDisplay(true), '->run() runs the help command if -h is passed');

        $tester->run(['command' => 'list', '--help' => true], ['decorated' => false]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run3.txt', $tester->getDisplay(true), '->run() displays the help if --help is passed');

        $tester->run(['command' => 'list', '-h' => true], ['decorated' => false]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run3.txt', $tester->getDisplay(true), '->run() displays the help if -h is passed');

        $tester->run(['--ansi' => true]);
        $this->assertTrue($tester->getOutput()->isDecorated(), '->run() forces color output if --ansi is passed');

        $tester->run(['--no-ansi' => true]);
        $this->assertFalse($tester->getOutput()->isDecorated(), '->run() forces color output to be disabled if --no-ansi is passed');

        $tester->run(['--version' => true], ['decorated' => false]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run4.txt', $tester->getDisplay(true), '->run() displays the program version if --version is passed');

        $tester->run(['-V' => true], ['decorated' => false]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run4.txt', $tester->getDisplay(true), '->run() displays the program version if -v is passed');

        $tester->run(['command' => 'list', '--quiet' => true]);
        $this->assertSame('', $tester->getDisplay(), '->run() removes all output if --quiet is passed');
        $this->assertFalse($tester->getInput()->isInteractive(), '->run() sets off the interactive mode if --quiet is passed');

        $tester->run(['command' => 'list', '-q' => true]);
        $this->assertSame('', $tester->getDisplay(), '->run() removes all output if -q is passed');
        $this->assertFalse($tester->getInput()->isInteractive(), '->run() sets off the interactive mode if -q is passed');

        $tester->run(['command' => 'list', '--verbose' => true]);
        $this->assertSame(Output::VERBOSITY_VERBOSE, $tester->getOutput()->getVerbosity(), '->run() sets the output to verbose if --verbose is passed');

        $tester->run(['command' => 'list', '--verbose' => 1]);
        $this->assertSame(Output::VERBOSITY_VERBOSE, $tester->getOutput()->getVerbosity(), '->run() sets the output to verbose if --verbose=1 is passed');

        $tester->run(['command' => 'list', '--verbose' => 2]);
        $this->assertSame(Output::VERBOSITY_VERY_VERBOSE, $tester->getOutput()->getVerbosity(), '->run() sets the output to very verbose if --verbose=2 is passed');

        $tester->run(['command' => 'list', '--verbose' => 3]);
        $this->assertSame(Output::VERBOSITY_DEBUG, $tester->getOutput()->getVerbosity(), '->run() sets the output to debug if --verbose=3 is passed');

        $tester->run(['command' => 'list', '--verbose' => 4]);
        $this->assertSame(Output::VERBOSITY_VERBOSE, $tester->getOutput()->getVerbosity(), '->run() sets the output to verbose if unknown --verbose level is passed');

        $tester->run(['command' => 'list', '-v' => true]);
        $this->assertSame(Output::VERBOSITY_VERBOSE, $tester->getOutput()->getVerbosity(), '->run() sets the output to verbose if -v is passed');

        $tester->run(['command' => 'list', '-vv' => true]);
        $this->assertSame(Output::VERBOSITY_VERY_VERBOSE, $tester->getOutput()->getVerbosity(), '->run() sets the output to verbose if -v is passed');

        $tester->run(['command' => 'list', '-vvv' => true]);
        $this->assertSame(Output::VERBOSITY_DEBUG, $tester->getOutput()->getVerbosity(), '->run() sets the output to verbose if -v is passed');

        $tester->run(['command' => 'help', '--help' => true], ['decorated' => false]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run5.txt', $tester->getDisplay(true), '->run() displays the help if --help is passed');

        $tester->run(['command' => 'help', '-h' => true], ['decorated' => false]);
        $this->assertStringEqualsFile(self::$fixturesPath.'/application_run5.txt', $tester->getDisplay(true), '->run() displays the help if -h is passed');

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->add(new \FooCommand());
        $tester = new ApplicationTester($application);

        $tester->run(['command' => 'foo:bar', '--no-interaction' => true], ['decorated' => false]);
        $this->assertSame('called'.\PHP_EOL, $tester->getDisplay(), '->run() does not call interact() if --no-interaction is passed');

        $tester->run(['command' => 'foo:bar', '-n' => true], ['decorated' => false]);
        $this->assertSame('called'.\PHP_EOL, $tester->getDisplay(), '->run() does not call interact() if -n is passed');
    }

    public function testRunWithGlobalOptionAndNoCommand()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->getDefinition()->addOption(new InputOption('foo', 'f', InputOption::VALUE_OPTIONAL));

        $output = new StreamOutput(fopen('php://memory', 'w', false));
        $input = new ArgvInput(['cli.php', '--foo', 'bar']);

        $this->assertSame(0, $application->run($input, $output));
    }

    /**
     * Issue #9285.
     *
     * If the "verbose" option is just before an argument in ArgvInput,
     * an argument value should not be treated as verbosity value.
     * This test will fail with "Not enough arguments." if broken
     */
    public function testVerboseValueNotBreakArguments()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->add(new \FooCommand());

        $output = new StreamOutput(fopen('php://memory', 'w', false));

        $input = new ArgvInput(['cli.php', '-v', 'foo:bar']);
        $application->run($input, $output);

        $this->addToAssertionCount(1);

        $input = new ArgvInput(['cli.php', '--verbose', 'foo:bar']);
        $application->run($input, $output);

        $this->addToAssertionCount(1);
    }

    public function testRunReturnsIntegerExitCode()
    {
        $exception = new \Exception('', 4);

        $application = $this->getMockBuilder(Application::class)->onlyMethods(['doRun'])->getMock();
        $application->setAutoExit(false);
        $application->expects($this->once())
            ->method('doRun')
            ->willThrowException($exception);

        $exitCode = $application->run(new ArrayInput([]), new NullOutput());

        $this->assertSame(4, $exitCode, '->run() returns integer exit code extracted from raised exception');
    }

    public function testRunDispatchesIntegerExitCode()
    {
        $passedRightValue = false;

        // We can assume here that some other test asserts that the event is dispatched at all
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('console.terminate', function (ConsoleTerminateEvent $event) use (&$passedRightValue) {
            $passedRightValue = (4 === $event->getExitCode());
        });

        $application = new Application();
        $application->setDispatcher($dispatcher);
        $application->setAutoExit(false);

        $application->register('test')->setCode(function (InputInterface $input, OutputInterface $output) {
            throw new \Exception('', 4);
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'test']);

        $this->assertTrue($passedRightValue, '-> exit code 4 was passed in the console.terminate event');
    }

    public function testRunReturnsExitCodeOneForExceptionCodeZero()
    {
        $exception = new \Exception('', 0);

        $application = $this->getMockBuilder(Application::class)->onlyMethods(['doRun'])->getMock();
        $application->setAutoExit(false);
        $application->expects($this->once())
            ->method('doRun')
            ->willThrowException($exception);

        $exitCode = $application->run(new ArrayInput([]), new NullOutput());

        $this->assertSame(1, $exitCode, '->run() returns exit code 1 when exception code is 0');
    }

    public function testRunDispatchesExitCodeOneForExceptionCodeZero()
    {
        $passedRightValue = false;

        // We can assume here that some other test asserts that the event is dispatched at all
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('console.terminate', function (ConsoleTerminateEvent $event) use (&$passedRightValue) {
            $passedRightValue = (1 === $event->getExitCode());
        });

        $application = new Application();
        $application->setDispatcher($dispatcher);
        $application->setAutoExit(false);

        $application->register('test')->setCode(function (InputInterface $input, OutputInterface $output) {
            throw new \Exception();
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'test']);

        $this->assertTrue($passedRightValue, '-> exit code 1 was passed in the console.terminate event');
    }

    /**
     * @testWith [-1]
     *           [-32000]
     */
    public function testRunReturnsExitCodeOneForNegativeExceptionCode($exceptionCode)
    {
        $exception = new \Exception('', $exceptionCode);

        $application = $this->getMockBuilder(Application::class)->onlyMethods(['doRun'])->getMock();
        $application->setAutoExit(false);
        $application->expects($this->once())
            ->method('doRun')
            ->willThrowException($exception);

        $exitCode = $application->run(new ArrayInput([]), new NullOutput());

        $this->assertSame(1, $exitCode, '->run() returns exit code 1 when exception code is '.$exceptionCode);
    }

    public function testAddingOptionWithDuplicateShortcut()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('An option with shortcut "e" already exists.');
        $dispatcher = new EventDispatcher();
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->setDispatcher($dispatcher);

        $application->getDefinition()->addOption(new InputOption('--env', '-e', InputOption::VALUE_REQUIRED, 'Environment'));

        $application
            ->register('foo')
            ->setAliases(['f'])
            ->setDefinition([new InputOption('survey', 'e', InputOption::VALUE_REQUIRED, 'My option with a shortcut.')])
            ->setCode(function (InputInterface $input, OutputInterface $output) {})
        ;

        $input = new ArrayInput(['command' => 'foo']);
        $output = new NullOutput();

        $application->run($input, $output);
    }

    /**
     * @dataProvider getAddingAlreadySetDefinitionElementData
     */
    public function testAddingAlreadySetDefinitionElementData($def)
    {
        $this->expectException(\LogicException::class);
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application
            ->register('foo')
            ->setDefinition([$def])
            ->setCode(function (InputInterface $input, OutputInterface $output) {})
        ;

        $input = new ArrayInput(['command' => 'foo']);
        $output = new NullOutput();
        $application->run($input, $output);
    }

    public static function getAddingAlreadySetDefinitionElementData()
    {
        return [
            [new InputArgument('command', InputArgument::REQUIRED)],
            [new InputOption('quiet', '', InputOption::VALUE_NONE)],
            [new InputOption('query', 'q', InputOption::VALUE_NONE)],
        ];
    }

    public function testGetDefaultHelperSetReturnsDefaultValues()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $helperSet = $application->getHelperSet();

        $this->assertTrue($helperSet->has('formatter'));
    }

    public function testAddingSingleHelperSetOverwritesDefaultValues()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $application->setHelperSet(new HelperSet([new FormatterHelper()]));

        $helperSet = $application->getHelperSet();

        $this->assertTrue($helperSet->has('formatter'));

        // no other default helper set should be returned
        $this->assertFalse($helperSet->has('dialog'));
        $this->assertFalse($helperSet->has('progress'));
    }

    public function testOverwritingDefaultHelperSetOverwritesDefaultValues()
    {
        $application = new CustomApplication();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $application->setHelperSet(new HelperSet([new FormatterHelper()]));

        $helperSet = $application->getHelperSet();

        $this->assertTrue($helperSet->has('formatter'));

        // no other default helper set should be returned
        $this->assertFalse($helperSet->has('dialog'));
        $this->assertFalse($helperSet->has('progress'));
    }

    public function testGetDefaultInputDefinitionReturnsDefaultValues()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $inputDefinition = $application->getDefinition();

        $this->assertTrue($inputDefinition->hasArgument('command'));

        $this->assertTrue($inputDefinition->hasOption('help'));
        $this->assertTrue($inputDefinition->hasOption('quiet'));
        $this->assertTrue($inputDefinition->hasOption('verbose'));
        $this->assertTrue($inputDefinition->hasOption('version'));
        $this->assertTrue($inputDefinition->hasOption('ansi'));
        $this->assertTrue($inputDefinition->hasNegation('no-ansi'));
        $this->assertFalse($inputDefinition->hasOption('no-ansi'));
        $this->assertTrue($inputDefinition->hasOption('no-interaction'));
    }

    public function testOverwritingDefaultInputDefinitionOverwritesDefaultValues()
    {
        $application = new CustomApplication();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $inputDefinition = $application->getDefinition();

        // check whether the default arguments and options are not returned any more
        $this->assertFalse($inputDefinition->hasArgument('command'));

        $this->assertFalse($inputDefinition->hasOption('help'));
        $this->assertFalse($inputDefinition->hasOption('quiet'));
        $this->assertFalse($inputDefinition->hasOption('verbose'));
        $this->assertFalse($inputDefinition->hasOption('version'));
        $this->assertFalse($inputDefinition->hasOption('ansi'));
        $this->assertFalse($inputDefinition->hasNegation('no-ansi'));
        $this->assertFalse($inputDefinition->hasOption('no-interaction'));

        $this->assertTrue($inputDefinition->hasOption('custom'));
    }

    public function testSettingCustomInputDefinitionOverwritesDefaultValues()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $application->setDefinition(new InputDefinition([new InputOption('--custom', '-c', InputOption::VALUE_NONE, 'Set the custom input definition.')]));

        $inputDefinition = $application->getDefinition();

        // check whether the default arguments and options are not returned any more
        $this->assertFalse($inputDefinition->hasArgument('command'));

        $this->assertFalse($inputDefinition->hasOption('help'));
        $this->assertFalse($inputDefinition->hasOption('quiet'));
        $this->assertFalse($inputDefinition->hasOption('verbose'));
        $this->assertFalse($inputDefinition->hasOption('version'));
        $this->assertFalse($inputDefinition->hasOption('ansi'));
        $this->assertFalse($inputDefinition->hasNegation('no-ansi'));
        $this->assertFalse($inputDefinition->hasOption('no-interaction'));

        $this->assertTrue($inputDefinition->hasOption('custom'));
    }

    public function testRunWithDispatcher()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setDispatcher($this->getDispatcher());

        $application->register('foo')->setCode(function (InputInterface $input, OutputInterface $output) {
            $output->write('foo.');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'foo']);
        $this->assertEquals('before.foo.after.'.\PHP_EOL, $tester->getDisplay());
    }

    public function testRunWithExceptionAndDispatcher()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('error');
        $application = new Application();
        $application->setDispatcher($this->getDispatcher());
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $application->register('foo')->setCode(function (InputInterface $input, OutputInterface $output) {
            throw new \RuntimeException('foo');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'foo']);
    }

    public function testRunDispatchesAllEventsWithException()
    {
        $application = new Application();
        $application->setDispatcher($this->getDispatcher());
        $application->setAutoExit(false);

        $application->register('foo')->setCode(function (InputInterface $input, OutputInterface $output) {
            $output->write('foo.');

            throw new \RuntimeException('foo');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'foo']);
        $this->assertStringContainsString('before.foo.error.after.', $tester->getDisplay());
    }

    public function testRunDispatchesAllEventsWithExceptionInListener()
    {
        $dispatcher = $this->getDispatcher();
        $dispatcher->addListener('console.command', function () {
            throw new \RuntimeException('foo');
        });

        $application = new Application();
        $application->setDispatcher($dispatcher);
        $application->setAutoExit(false);

        $application->register('foo')->setCode(function (InputInterface $input, OutputInterface $output) {
            $output->write('foo.');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'foo']);
        $this->assertStringContainsString('before.error.after.', $tester->getDisplay());
    }

    public function testRunWithError()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $application->register('dym')->setCode(function (InputInterface $input, OutputInterface $output) {
            $output->write('dym.');

            throw new \Error('dymerr');
        });

        $tester = new ApplicationTester($application);

        try {
            $tester->run(['command' => 'dym']);
            $this->fail('Error expected.');
        } catch (\Error $e) {
            $this->assertSame('dymerr', $e->getMessage());
        }
    }

    public function testRunAllowsErrorListenersToSilenceTheException()
    {
        $dispatcher = $this->getDispatcher();
        $dispatcher->addListener('console.error', function (ConsoleErrorEvent $event) {
            $event->getOutput()->write('silenced.');

            $event->setExitCode(0);
        });

        $dispatcher->addListener('console.command', function () {
            throw new \RuntimeException('foo');
        });

        $application = new Application();
        $application->setDispatcher($dispatcher);
        $application->setAutoExit(false);

        $application->register('foo')->setCode(function (InputInterface $input, OutputInterface $output) {
            $output->write('foo.');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'foo']);
        $this->assertStringContainsString('before.error.silenced.after.', $tester->getDisplay());
        $this->assertEquals(ConsoleCommandEvent::RETURN_CODE_DISABLED, $tester->getStatusCode());
    }

    public function testConsoleErrorEventIsTriggeredOnCommandNotFound()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('console.error', function (ConsoleErrorEvent $event) {
            $this->assertNull($event->getCommand());
            $this->assertInstanceOf(CommandNotFoundException::class, $event->getError());
            $event->getOutput()->write('silenced command not found');
        });

        $application = new Application();
        $application->setDispatcher($dispatcher);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'unknown']);
        $this->assertStringContainsString('silenced command not found', $tester->getDisplay());
        $this->assertEquals(1, $tester->getStatusCode());
    }

    public function testErrorIsRethrownIfNotHandledByConsoleErrorEvent()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->setDispatcher(new EventDispatcher());

        $application->register('dym')->setCode(function () {
            throw new \Error('Something went wrong.');
        });

        $tester = new ApplicationTester($application);

        try {
            $tester->run(['command' => 'dym']);
            $this->fail('->run() should rethrow PHP errors if not handled via ConsoleErrorEvent.');
        } catch (\Error $e) {
            $this->assertSame('Something went wrong.', $e->getMessage());
        }
    }

    public function testRunWithErrorAndDispatcher()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('error');
        $application = new Application();
        $application->setDispatcher($this->getDispatcher());
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $application->register('dym')->setCode(function (InputInterface $input, OutputInterface $output) {
            $output->write('dym.');

            throw new \Error('dymerr');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'dym']);
        $this->assertStringContainsString('before.dym.error.after.', $tester->getDisplay(), 'The PHP error did not dispatch events');
    }

    public function testRunDispatchesAllEventsWithError()
    {
        $application = new Application();
        $application->setDispatcher($this->getDispatcher());
        $application->setAutoExit(false);

        $application->register('dym')->setCode(function (InputInterface $input, OutputInterface $output) {
            $output->write('dym.');

            throw new \Error('dymerr');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'dym']);
        $this->assertStringContainsString('before.dym.error.after.', $tester->getDisplay(), 'The PHP error did not dispatch events');
    }

    public function testRunWithErrorFailingStatusCode()
    {
        $application = new Application();
        $application->setDispatcher($this->getDispatcher());
        $application->setAutoExit(false);

        $application->register('dus')->setCode(function (InputInterface $input, OutputInterface $output) {
            $output->write('dus.');

            throw new \Error('duserr');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'dus']);
        $this->assertSame(1, $tester->getStatusCode(), 'Status code should be 1');
    }

    public function testRunWithDispatcherSkippingCommand()
    {
        $application = new Application();
        $application->setDispatcher($this->getDispatcher(true));
        $application->setAutoExit(false);

        $application->register('foo')->setCode(function (InputInterface $input, OutputInterface $output) {
            $output->write('foo.');
        });

        $tester = new ApplicationTester($application);
        $exitCode = $tester->run(['command' => 'foo']);
        $this->assertStringContainsString('before.after.', $tester->getDisplay());
        $this->assertEquals(ConsoleCommandEvent::RETURN_CODE_DISABLED, $exitCode);
    }

    public function testRunWithDispatcherAccessingInputOptions()
    {
        $noInteractionValue = null;
        $quietValue = null;

        $dispatcher = $this->getDispatcher();
        $dispatcher->addListener('console.command', function (ConsoleCommandEvent $event) use (&$noInteractionValue, &$quietValue) {
            $input = $event->getInput();

            $noInteractionValue = $input->getOption('no-interaction');
            $quietValue = $input->getOption('quiet');
        });

        $application = new Application();
        $application->setDispatcher($dispatcher);
        $application->setAutoExit(false);

        $application->register('foo')->setCode(function (InputInterface $input, OutputInterface $output) {
            $output->write('foo.');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'foo', '--no-interaction' => true]);

        $this->assertTrue($noInteractionValue);
        $this->assertFalse($quietValue);
    }

    public function testRunWithDispatcherAddingInputOptions()
    {
        $extraValue = null;

        $dispatcher = $this->getDispatcher();
        $dispatcher->addListener('console.command', function (ConsoleCommandEvent $event) use (&$extraValue) {
            $definition = $event->getCommand()->getDefinition();
            $input = $event->getInput();

            $definition->addOption(new InputOption('extra', null, InputOption::VALUE_REQUIRED));
            $input->bind($definition);

            $extraValue = $input->getOption('extra');
        });

        $application = new Application();
        $application->setDispatcher($dispatcher);
        $application->setAutoExit(false);

        $application->register('foo')->setCode(function (InputInterface $input, OutputInterface $output) {
            $output->write('foo.');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'foo', '--extra' => 'some test value']);

        $this->assertEquals('some test value', $extraValue);
    }

    public function testSetRunCustomDefaultCommand()
    {
        $command = new \FooCommand();

        $application = new Application();
        $application->setAutoExit(false);
        $application->add($command);
        $application->setDefaultCommand($command->getName());

        $tester = new ApplicationTester($application);
        $tester->run([], ['interactive' => false]);
        $this->assertEquals('called'.\PHP_EOL, $tester->getDisplay(), 'Application runs the default set command if different from \'list\' command');

        $application = new CustomDefaultCommandApplication();
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run([], ['interactive' => false]);

        $this->assertEquals('called'.\PHP_EOL, $tester->getDisplay(), 'Application runs the default set command if different from \'list\' command');
    }

    public function testSetRunCustomDefaultCommandWithOption()
    {
        $command = new \FooOptCommand();

        $application = new Application();
        $application->setAutoExit(false);
        $application->add($command);
        $application->setDefaultCommand($command->getName());

        $tester = new ApplicationTester($application);
        $tester->run(['--fooopt' => 'opt'], ['interactive' => false]);

        $this->assertEquals('called'.\PHP_EOL.'opt'.\PHP_EOL, $tester->getDisplay(), 'Application runs the default set command if different from \'list\' command');
    }

    public function testSetRunCustomSingleCommand()
    {
        $command = new \FooCommand();

        $application = new Application();
        $application->setAutoExit(false);
        $application->add($command);
        $application->setDefaultCommand($command->getName(), true);

        $tester = new ApplicationTester($application);

        $tester->run([]);
        $this->assertStringContainsString('called', $tester->getDisplay());

        $tester->run(['--help' => true]);
        $this->assertStringContainsString('The foo:bar command', $tester->getDisplay());
    }

    public function testRunLazyCommandService()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new AddConsoleCommandPass());
        $container
            ->register('lazy-command', LazyTestCommand::class)
            ->addTag('console.command', ['command' => 'lazy:command'])
            ->addTag('console.command', ['command' => 'lazy:alias'])
            ->addTag('console.command', ['command' => 'lazy:alias2']);
        $container->compile();

        $application = new Application();
        $application->setCommandLoader($container->get('console.command_loader'));
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);

        $tester->run(['command' => 'lazy:command']);
        $this->assertSame("lazy-command called\n", $tester->getDisplay(true));

        $tester->run(['command' => 'lazy:alias']);
        $this->assertSame("lazy-command called\n", $tester->getDisplay(true));

        $tester->run(['command' => 'lazy:alias2']);
        $this->assertSame("lazy-command called\n", $tester->getDisplay(true));

        $command = $application->get('lazy:command');
        $this->assertSame(['lazy:alias', 'lazy:alias2'], $command->getAliases());
    }

    public function testGetDisabledLazyCommand()
    {
        $this->expectException(CommandNotFoundException::class);
        $application = new Application();
        $application->setCommandLoader(new FactoryCommandLoader(['disabled' => function () { return new DisabledCommand(); }]));
        $application->get('disabled');
    }

    public function testHasReturnsFalseForDisabledLazyCommand()
    {
        $application = new Application();
        $application->setCommandLoader(new FactoryCommandLoader(['disabled' => function () { return new DisabledCommand(); }]));
        $this->assertFalse($application->has('disabled'));
    }

    public function testAllExcludesDisabledLazyCommand()
    {
        $application = new Application();
        $application->setCommandLoader(new FactoryCommandLoader(['disabled' => function () { return new DisabledCommand(); }]));
        $this->assertArrayNotHasKey('disabled', $application->all());
    }

    public function testFindAlternativesDoesNotLoadSameNamespaceCommandsOnExactMatch()
    {
        $application = new Application();
        $application->setAutoExit(false);

        $loaded = [];

        $application->setCommandLoader(new FactoryCommandLoader([
            'foo:bar' => function () use (&$loaded) {
                $loaded['foo:bar'] = true;

                return (new Command('foo:bar'))->setCode(function () {});
            },
            'foo' => function () use (&$loaded) {
                $loaded['foo'] = true;

                return (new Command('foo'))->setCode(function () {});
            },
        ]));

        $application->run(new ArrayInput(['command' => 'foo']), new NullOutput());

        $this->assertSame(['foo' => true], $loaded);
    }

    protected function getDispatcher($skipCommand = false)
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('console.command', function (ConsoleCommandEvent $event) use ($skipCommand) {
            $event->getOutput()->write('before.');

            if ($skipCommand) {
                $event->disableCommand();
            }
        });
        $dispatcher->addListener('console.terminate', function (ConsoleTerminateEvent $event) use ($skipCommand) {
            $event->getOutput()->writeln('after.');

            if (!$skipCommand) {
                $event->setExitCode(ConsoleCommandEvent::RETURN_CODE_DISABLED);
            }
        });
        $dispatcher->addListener('console.error', function (ConsoleErrorEvent $event) {
            $event->getOutput()->write('error.');

            $event->setError(new \LogicException('error.', $event->getExitCode(), $event->getError()));
        });

        return $dispatcher;
    }

    public function testErrorIsRethrownIfNotHandledByConsoleErrorEventWithCatchingEnabled()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setDispatcher(new EventDispatcher());

        $application->register('dym')->setCode(function () {
            throw new \Error('Something went wrong.');
        });

        $tester = new ApplicationTester($application);

        try {
            $tester->run(['command' => 'dym']);
            $this->fail('->run() should rethrow PHP errors if not handled via ConsoleErrorEvent.');
        } catch (\Error $e) {
            $this->assertSame('Something went wrong.', $e->getMessage());
        }
    }

    public function testThrowingErrorListener()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('foo');
        $dispatcher = $this->getDispatcher();
        $dispatcher->addListener('console.error', function (ConsoleErrorEvent $event) {
            throw new \RuntimeException('foo');
        });

        $dispatcher->addListener('console.command', function () {
            throw new \RuntimeException('bar');
        });

        $application = new Application();
        $application->setDispatcher($dispatcher);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $application->register('foo')->setCode(function (InputInterface $input, OutputInterface $output) {
            $output->write('foo.');
        });

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'foo']);
    }

    public function testCommandNameMismatchWithCommandLoaderKeyThrows()
    {
        $this->expectException(CommandNotFoundException::class);
        $this->expectExceptionMessage('The "test" command cannot be found because it is registered under multiple names. Make sure you don\'t set a different name via constructor or "setName()".');

        $app = new Application();
        $loader = new FactoryCommandLoader([
            'test' => static function () { return new Command('test-command'); },
        ]);

        $app->setCommandLoader($loader);
        $app->get('test');
    }

    /**
     * @requires extension pcntl
     */
    public function testSignalListenerNotCalledByDefault()
    {
        $command = new SignableCommand(false);

        $dispatcherCalled = false;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('console.signal', function () use (&$dispatcherCalled) {
            $dispatcherCalled = true;
        });

        $application = $this->createSignalableApplication($command, $dispatcher);

        $this->assertSame(0, $application->run(new ArrayInput(['signal'])));
        $this->assertFalse($command->signaled);
        $this->assertFalse($dispatcherCalled);
    }

    /**
     * @requires extension pcntl
     */
    public function testSignalListener()
    {
        $command = new SignableCommand();

        $dispatcherCalled = false;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('console.signal', function () use (&$dispatcherCalled) {
            $dispatcherCalled = true;
        });

        $application = $this->createSignalableApplication($command, $dispatcher);

        $this->assertSame(1, $application->run(new ArrayInput(['signal'])));
        $this->assertTrue($dispatcherCalled);
        $this->assertTrue($command->signaled);
    }

    /**
     * @requires extension pcntl
     */
    public function testSignalSubscriberNotCalledByDefault()
    {
        $command = new BaseSignableCommand(false);

        $subscriber = new SignalEventSubscriber();
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($subscriber);

        $application = $this->createSignalableApplication($command, $dispatcher);

        $this->assertSame(0, $application->run(new ArrayInput(['signal'])));
        $this->assertFalse($subscriber->signaled);
    }

    /**
     * @requires extension pcntl
     */
    public function testSignalSubscriber()
    {
        $command = new BaseSignableCommand();

        $subscriber1 = new SignalEventSubscriber();
        $subscriber2 = new SignalEventSubscriber();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($subscriber1);
        $dispatcher->addSubscriber($subscriber2);

        $application = $this->createSignalableApplication($command, $dispatcher);

        $this->assertSame(1, $application->run(new ArrayInput(['signal'])));
        $this->assertTrue($subscriber1->signaled);
        $this->assertTrue($subscriber2->signaled);
    }

    /**
     * @requires extension pcntl
     */
    public function testSetSignalsToDispatchEvent()
    {
        if (!\defined('SIGUSR1')) {
            $this->markTestSkipped('SIGUSR1 not available');
        }

        $command = new BaseSignableCommand();

        $subscriber = new SignalEventSubscriber();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($subscriber);

        // Since there is no signal handler, and by default PHP will stop even
        // on SIGUSR1, we need to register a blank handler to avoid the process
        // being stopped.
        $blankHandlerSignaled = false;
        pcntl_signal(\SIGUSR1, function () use (&$blankHandlerSignaled) {
            $blankHandlerSignaled = true;
        });

        $application = $this->createSignalableApplication($command, $dispatcher);
        $application->setSignalsToDispatchEvent(\SIGUSR2);
        $this->assertSame(0, $application->run(new ArrayInput(['signal'])));
        $this->assertFalse($subscriber->signaled);
        $this->assertTrue($blankHandlerSignaled);

        // We reset the blank handler to false to make sure it is called again
        $blankHandlerSignaled = false;

        $application = $this->createSignalableApplication($command, $dispatcher);
        $application->setSignalsToDispatchEvent(\SIGUSR1);
        $this->assertSame(1, $application->run(new ArrayInput(['signal'])));
        $this->assertTrue($subscriber->signaled);
        $this->assertTrue($blankHandlerSignaled);

        // And now we test without the blank handler
        $blankHandlerSignaled = false;
        pcntl_signal(\SIGUSR1, SIG_DFL);

        $application = $this->createSignalableApplication($command, $dispatcher);
        $application->setSignalsToDispatchEvent(\SIGUSR1);
        $this->assertSame(1, $application->run(new ArrayInput(['signal'])));
        $this->assertTrue($subscriber->signaled);
        $this->assertFalse($blankHandlerSignaled);
    }

    public function testSignalableCommandInterfaceWithoutSignals()
    {
        if (!\defined('SIGUSR1')) {
            $this->markTestSkipped('SIGUSR1 not available');
        }

        $command = new SignableCommand(false);

        $dispatcher = new EventDispatcher();
        $application = new Application();
        $application->setAutoExit(false);
        $application->setDispatcher($dispatcher);
        $application->add($command);
        $this->assertSame(0, $application->run(new ArrayInput(['signal'])));
    }

    public function testSignalableCommandHandlerCalledAfterEventListener()
    {
        if (!\defined('SIGUSR1')) {
            $this->markTestSkipped('SIGUSR1 not available');
        }

        $command = new SignableCommand();

        $subscriber = new SignalEventSubscriber();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($subscriber);

        $application = $this->createSignalableApplication($command, $dispatcher);
        $application->setSignalsToDispatchEvent(\SIGUSR1);
        $this->assertSame(1, $application->run(new ArrayInput(['signal'])));
        $this->assertSame([SignalEventSubscriber::class, SignableCommand::class], $command->signalHandlers);
    }

    /**
     * @group tty
     */
    public function testSignalableRestoresStty()
    {
        if (!Terminal::hasSttyAvailable()) {
            $this->markTestSkipped('stty not available');
        }

        if (!SignalRegistry::isSupported()) {
            $this->markTestSkipped('pcntl signals not available');
        }

        $previousSttyMode = shell_exec('stty -g');

        $p = new Process(['php', __DIR__.'/Fixtures/application_signalable.php']);
        $p->setTty(true);
        $p->start();

        for ($i = 0; $i < 10 && shell_exec('stty -g') === $previousSttyMode; ++$i) {
            usleep(100000);
        }

        $this->assertNotSame($previousSttyMode, shell_exec('stty -g'));
        $p->signal(\SIGINT);
        $p->wait();

        $sttyMode = shell_exec('stty -g');
        shell_exec('stty '.$previousSttyMode);

        $this->assertSame($previousSttyMode, $sttyMode);
    }

    private function createSignalableApplication(Command $command, ?EventDispatcherInterface $dispatcher): Application
    {
        $application = new Application();
        $application->setAutoExit(false);
        if ($dispatcher) {
            $application->setDispatcher($dispatcher);
        }
        $application->add(new LazyCommand('signal', [], '', false, function () use ($command) { return $command; }, true));

        return $application;
    }
}

class CustomApplication extends Application
{
    /**
     * Overwrites the default input definition.
     */
    protected function getDefaultInputDefinition(): InputDefinition
    {
        return new InputDefinition([new InputOption('--custom', '-c', InputOption::VALUE_NONE, 'Set the custom input definition.')]);
    }

    /**
     * Gets the default helper set with the helpers that should always be available.
     */
    protected function getDefaultHelperSet(): HelperSet
    {
        return new HelperSet([new FormatterHelper()]);
    }
}

class CustomDefaultCommandApplication extends Application
{
    /**
     * Overwrites the constructor in order to set a different default command.
     */
    public function __construct()
    {
        parent::__construct();

        $command = new \FooCommand();
        $this->add($command);
        $this->setDefaultCommand($command->getName());
    }
}

class LazyTestCommand extends Command
{
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('lazy-command called');

        return 0;
    }
}

class DisabledCommand extends Command
{
    public function isEnabled(): bool
    {
        return false;
    }
}

class BaseSignableCommand extends Command
{
    public $signaled = false;
    public $signalHandlers = [];
    public $loop = 1000;
    private $emitsSignal;

    protected static $defaultName = 'signal';

    public function __construct(bool $emitsSignal = true)
    {
        parent::__construct();
        $this->emitsSignal = $emitsSignal;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->emitsSignal) {
            posix_kill(posix_getpid(), \SIGUSR1);
        }

        for ($i = 0; $i < $this->loop; ++$i) {
            usleep(100);
            if ($this->signaled) {
                return 1;
            }
        }

        return 0;
    }
}

class SignableCommand extends BaseSignableCommand implements SignalableCommandInterface
{
    protected static $defaultName = 'signal';

    public function getSubscribedSignals(): array
    {
        return SignalRegistry::isSupported() ? [\SIGUSR1] : [];
    }

    public function handleSignal(int $signal): void
    {
        $this->signaled = true;
        $this->signalHandlers[] = __CLASS__;
    }
}

class SignalEventSubscriber implements EventSubscriberInterface
{
    public $signaled = false;

    public function onSignal(ConsoleSignalEvent $event): void
    {
        $this->signaled = true;
        $event->getCommand()->signaled = true;
        $event->getCommand()->signalHandlers[] = __CLASS__;
    }

    public static function getSubscribedEvents(): array
    {
        return ['console.signal' => 'onSignal'];
    }
}
