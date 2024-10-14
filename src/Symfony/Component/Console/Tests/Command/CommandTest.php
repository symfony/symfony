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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class CommandTest extends TestCase
{
    protected static string $fixturesPath;

    public static function setUpBeforeClass(): void
    {
        self::$fixturesPath = __DIR__.'/../Fixtures/';
        require_once self::$fixturesPath.'/TestCommand.php';
    }

    public function testConstructor()
    {
        $command = new Command('foo:bar');
        $this->assertEquals('foo:bar', $command->getName(), '__construct() takes the command name as its first argument');
    }

    public function testCommandNameCannotBeEmpty()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The command defined in "Symfony\Component\Console\Command\Command" cannot have an empty name.');
        (new Application())->add(new Command());
    }

    public function testSetApplication()
    {
        $application = new Application();
        $command = new \TestCommand();
        $command->setApplication($application);
        $this->assertEquals($application, $command->getApplication(), '->setApplication() sets the current application');
        $this->assertEquals($application->getHelperSet(), $command->getHelperSet());
    }

    public function testSetApplicationNull()
    {
        $command = new \TestCommand();
        $command->setApplication(null);
        $this->assertNull($command->getHelperSet());
    }

    public function testSetGetDefinition()
    {
        $command = new \TestCommand();
        $ret = $command->setDefinition($definition = new InputDefinition());
        $this->assertEquals($command, $ret, '->setDefinition() implements a fluent interface');
        $this->assertEquals($definition, $command->getDefinition(), '->setDefinition() sets the current InputDefinition instance');
        $command->setDefinition([new InputArgument('foo'), new InputOption('bar')]);
        $this->assertTrue($command->getDefinition()->hasArgument('foo'), '->setDefinition() also takes an array of InputArguments and InputOptions as an argument');
        $this->assertTrue($command->getDefinition()->hasOption('bar'), '->setDefinition() also takes an array of InputArguments and InputOptions as an argument');
        $command->setDefinition(new InputDefinition());
    }

    public function testAddArgument()
    {
        $command = new \TestCommand();
        $ret = $command->addArgument('foo');
        $this->assertEquals($command, $ret, '->addArgument() implements a fluent interface');
        $this->assertTrue($command->getDefinition()->hasArgument('foo'), '->addArgument() adds an argument to the command');
    }

    public function testAddArgumentFull()
    {
        $command = new \TestCommand();
        $command->addArgument('foo', InputArgument::OPTIONAL, 'Description', 'default', ['a', 'b']);
        $argument = $command->getDefinition()->getArgument('foo');
        $this->assertSame('Description', $argument->getDescription());
        $this->assertSame('default', $argument->getDefault());
        $this->assertTrue($argument->hasCompletion());
    }

    public function testAddOption()
    {
        $command = new \TestCommand();
        $ret = $command->addOption('foo');
        $this->assertEquals($command, $ret, '->addOption() implements a fluent interface');
        $this->assertTrue($command->getDefinition()->hasOption('foo'), '->addOption() adds an option to the command');
    }

    public function testAddOptionFull()
    {
        $command = new \TestCommand();
        $command->addOption('foo', ['f'], InputOption::VALUE_OPTIONAL, 'Description', 'default', ['a', 'b']);
        $option = $command->getDefinition()->getOption('foo');
        $this->assertSame('f', $option->getShortcut());
        $this->assertSame('Description', $option->getDescription());
        $this->assertSame('default', $option->getDefault());
        $this->assertTrue($option->hasCompletion());
    }

    public function testSetHidden()
    {
        $command = new \TestCommand();
        $command->setHidden();
        $this->assertTrue($command->isHidden());
    }

    public function testGetNamespaceGetNameSetName()
    {
        $command = new \TestCommand();
        $this->assertEquals('namespace:name', $command->getName(), '->getName() returns the command name');
        $command->setName('foo');
        $this->assertEquals('foo', $command->getName(), '->setName() sets the command name');

        $ret = $command->setName('foobar:bar');
        $this->assertEquals($command, $ret, '->setName() implements a fluent interface');
        $this->assertEquals('foobar:bar', $command->getName(), '->setName() sets the command name');
    }

    /**
     * @dataProvider provideInvalidCommandNames
     */
    public function testInvalidCommandNames($name)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Command name "%s" is invalid.', $name));

        (new \TestCommand())->setName($name);
    }

    public static function provideInvalidCommandNames(): array
    {
        return [
            [''],
            ['foo:'],
        ];
    }

    public function testGetSetDescription()
    {
        $command = new \TestCommand();
        $this->assertEquals('description', $command->getDescription(), '->getDescription() returns the description');
        $ret = $command->setDescription('description1');
        $this->assertEquals($command, $ret, '->setDescription() implements a fluent interface');
        $this->assertEquals('description1', $command->getDescription(), '->setDescription() sets the description');
    }

    public function testGetSetHelp()
    {
        $command = new \TestCommand();
        $this->assertEquals('help', $command->getHelp(), '->getHelp() returns the help');
        $ret = $command->setHelp('help1');
        $this->assertEquals($command, $ret, '->setHelp() implements a fluent interface');
        $this->assertEquals('help1', $command->getHelp(), '->setHelp() sets the help');
        $command->setHelp('');
        $this->assertEquals('', $command->getHelp(), '->getHelp() does not fall back to the description');
    }

    public function testGetProcessedHelp()
    {
        $command = new \TestCommand();
        $command->setHelp('The %command.name% command does... Example: %command.full_name%.');
        $this->assertStringContainsString('The namespace:name command does...', $command->getProcessedHelp(), '->getProcessedHelp() replaces %command.name% correctly');
        $this->assertStringNotContainsString('%command.full_name%', $command->getProcessedHelp(), '->getProcessedHelp() replaces %command.full_name%');

        $command = new \TestCommand();
        $command->setHelp('');
        $this->assertStringContainsString('description', $command->getProcessedHelp(), '->getProcessedHelp() falls back to the description');

        $command = new \TestCommand();
        $command->setHelp('The %command.name% command does... Example: %command.full_name%.');
        $application = new Application();
        $application->add($command);
        $application->setDefaultCommand('namespace:name', true);
        $this->assertStringContainsString('The namespace:name command does...', $command->getProcessedHelp(), '->getProcessedHelp() replaces %command.name% correctly in single command applications');
        $this->assertStringNotContainsString('%command.full_name%', $command->getProcessedHelp(), '->getProcessedHelp() replaces %command.full_name% in single command applications');
    }

    public function testGetSetAliases()
    {
        $command = new \TestCommand();
        $this->assertEquals(['name'], $command->getAliases(), '->getAliases() returns the aliases');
        $ret = $command->setAliases(['name1']);
        $this->assertEquals($command, $ret, '->setAliases() implements a fluent interface');
        $this->assertEquals(['name1'], $command->getAliases(), '->setAliases() sets the aliases');
    }

    public function testGetSynopsis()
    {
        $command = new \TestCommand();
        $command->addOption('foo');
        $command->addArgument('bar');
        $command->addArgument('info');
        $this->assertEquals('namespace:name [--foo] [--] [<bar> [<info>]]', $command->getSynopsis(), '->getSynopsis() returns the synopsis');
    }

    public function testAddGetUsages()
    {
        $command = new \TestCommand();
        $command->addUsage('foo1');
        $command->addUsage('foo2');
        $command->addUsages(['foo3', 'foo4']);
        $this->assertContains('namespace:name foo1', $command->getUsages());
        $this->assertContains('namespace:name foo2', $command->getUsages());
        $this->assertContains('namespace:name foo3', $command->getUsages());
        $this->assertContains('namespace:name foo4', $command->getUsages());
    }

    public function testGetHelper()
    {
        $application = new Application();
        $command = new \TestCommand();
        $command->setApplication($application);
        $formatterHelper = new FormatterHelper();
        $this->assertEquals($formatterHelper->getName(), $command->getHelper('formatter')->getName(), '->getHelper() returns the correct helper');
    }

    public function testGetHelperWithoutHelperSet()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot retrieve helper "formatter" because there is no HelperSet defined.');
        (new \TestCommand())->getHelper('formatter');
    }

    public function testMergeApplicationDefinition()
    {
        $application1 = new Application();
        $application1->getDefinition()->addArguments([new InputArgument('foo')]);
        $application1->getDefinition()->addOptions([new InputOption('bar')]);
        $command = new \TestCommand();
        $command->setApplication($application1);
        $command->setDefinition($definition = new InputDefinition([new InputArgument('bar'), new InputOption('foo')]));

        $r = new \ReflectionObject($command);
        $m = $r->getMethod('mergeApplicationDefinition');
        $m->invoke($command);
        $this->assertTrue($command->getDefinition()->hasArgument('foo'), '->mergeApplicationDefinition() merges the application arguments and the command arguments');
        $this->assertTrue($command->getDefinition()->hasArgument('bar'), '->mergeApplicationDefinition() merges the application arguments and the command arguments');
        $this->assertTrue($command->getDefinition()->hasOption('foo'), '->mergeApplicationDefinition() merges the application options and the command options');
        $this->assertTrue($command->getDefinition()->hasOption('bar'), '->mergeApplicationDefinition() merges the application options and the command options');

        $m->invoke($command);
        $this->assertEquals(3, $command->getDefinition()->getArgumentCount(), '->mergeApplicationDefinition() does not try to merge twice the application arguments and options');
    }

    public function testMergeApplicationDefinitionWithoutArgsThenWithArgsAddsArgs()
    {
        $application1 = new Application();
        $application1->getDefinition()->addArguments([new InputArgument('foo')]);
        $application1->getDefinition()->addOptions([new InputOption('bar')]);
        $command = new \TestCommand();
        $command->setApplication($application1);
        $command->setDefinition($definition = new InputDefinition([]));

        $r = new \ReflectionObject($command);
        $m = $r->getMethod('mergeApplicationDefinition');
        $m->invoke($command, false);
        $this->assertTrue($command->getDefinition()->hasOption('bar'), '->mergeApplicationDefinition(false) merges the application and the command options');
        $this->assertFalse($command->getDefinition()->hasArgument('foo'), '->mergeApplicationDefinition(false) does not merge the application arguments');

        $m->invoke($command, true);
        $this->assertTrue($command->getDefinition()->hasArgument('foo'), '->mergeApplicationDefinition(true) merges the application arguments and the command arguments');

        $m->invoke($command);
        $this->assertEquals(2, $command->getDefinition()->getArgumentCount(), '->mergeApplicationDefinition() does not try to merge twice the application arguments');
    }

    public function testRunInteractive()
    {
        $tester = new CommandTester(new \TestCommand());

        $tester->execute([], ['interactive' => true]);

        $this->assertEquals('interact called'.\PHP_EOL.'execute called'.\PHP_EOL, $tester->getDisplay(), '->run() calls the interact() method if the input is interactive');
    }

    public function testRunNonInteractive()
    {
        $tester = new CommandTester(new \TestCommand());

        $tester->execute([], ['interactive' => false]);

        $this->assertEquals('execute called'.\PHP_EOL, $tester->getDisplay(), '->run() does not call the interact() method if the input is not interactive');
    }

    public function testExecuteMethodNeedsToBeOverridden()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('You must override the execute() method in the concrete command class.');
        (new Command('foo'))->run(new StringInput(''), new NullOutput());
    }

    public function testRunWithInvalidOption()
    {
        $command = new \TestCommand();
        $tester = new CommandTester($command);

        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The "--bar" option does not exist.');

        $tester->execute(['--bar' => true]);
    }

    public function testRunWithApplication()
    {
        $command = new \TestCommand();
        $command->setApplication(new Application());
        $exitCode = $command->run(new StringInput(''), new NullOutput());

        $this->assertSame(0, $exitCode, '->run() returns an integer exit code');
    }

    public function testRunReturnsAlwaysInteger()
    {
        $command = new \TestCommand();

        $this->assertSame(0, $command->run(new StringInput(''), new NullOutput()));
    }

    public function testRunWithProcessTitle()
    {
        $command = new \TestCommand();
        $command->setApplication(new Application());
        $command->setProcessTitle('foo');
        $this->assertSame(0, $command->run(new StringInput(''), new NullOutput()));
        if (\function_exists('cli_set_process_title')) {
            if (null === @cli_get_process_title() && 'Darwin' === \PHP_OS) {
                $this->markTestSkipped('Running "cli_get_process_title" as an unprivileged user is not supported on MacOS.');
            }
            $this->assertEquals('foo', cli_get_process_title());
        }
    }

    public function testSetCode()
    {
        $command = new \TestCommand();
        $ret = $command->setCode(function (InputInterface $input, OutputInterface $output) {
            $output->writeln('from the code...');
        });
        $this->assertEquals($command, $ret, '->setCode() implements a fluent interface');
        $tester = new CommandTester($command);
        $tester->execute([]);
        $this->assertEquals('interact called'.\PHP_EOL.'from the code...'.\PHP_EOL, $tester->getDisplay());
    }

    public static function getSetCodeBindToClosureTests()
    {
        return [
            [true, 'not bound to the command'],
            [false, 'bound to the command'],
        ];
    }

    /**
     * @dataProvider getSetCodeBindToClosureTests
     */
    public function testSetCodeBindToClosure($previouslyBound, $expected)
    {
        $code = createClosure();
        if ($previouslyBound) {
            $code = $code->bindTo($this);
        }

        $command = new \TestCommand();
        $command->setCode($code);
        $tester = new CommandTester($command);
        $tester->execute([]);
        $this->assertEquals('interact called'.\PHP_EOL.$expected.\PHP_EOL, $tester->getDisplay());
    }

    public function testSetCodeWithStaticClosure()
    {
        $command = new \TestCommand();
        $command->setCode(self::createClosure());
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertEquals('interact called'.\PHP_EOL.'bound'.\PHP_EOL, $tester->getDisplay());
    }

    private static function createClosure()
    {
        return function (InputInterface $input, OutputInterface $output) {
            $output->writeln(isset($this) ? 'bound' : 'not bound');
        };
    }

    public function testSetCodeWithNonClosureCallable()
    {
        $command = new \TestCommand();
        $ret = $command->setCode($this->callableMethodCommand(...));
        $this->assertEquals($command, $ret, '->setCode() implements a fluent interface');
        $tester = new CommandTester($command);
        $tester->execute([]);
        $this->assertEquals('interact called'.\PHP_EOL.'from the code...'.\PHP_EOL, $tester->getDisplay());
    }

    public function callableMethodCommand(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('from the code...');
    }

    public function testSetCodeWithStaticAnonymousFunction()
    {
        $command = new \TestCommand();
        $command->setCode(static function (InputInterface $input, OutputInterface $output) {
            $output->writeln(isset($this) ? 'bound' : 'not bound');
        });
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertEquals('interact called'.\PHP_EOL.'not bound'.\PHP_EOL, $tester->getDisplay());
    }

    public function testCommandAttribute()
    {
        $this->assertSame('|foo|f', Php8Command::getDefaultName());
        $this->assertSame('desc', Php8Command::getDefaultDescription());

        $command = new Php8Command();

        $this->assertSame('foo', $command->getName());
        $this->assertSame('desc', $command->getDescription());
        $this->assertTrue($command->isHidden());
        $this->assertSame(['f'], $command->getAliases());
    }

    public function testAttributeOverridesProperty()
    {
        $this->assertSame('my:command', MyAnnotatedCommand::getDefaultName());
        $this->assertSame('This is a command I wrote all by myself', MyAnnotatedCommand::getDefaultDescription());

        $command = new MyAnnotatedCommand();

        $this->assertSame('my:command', $command->getName());
        $this->assertSame('This is a command I wrote all by myself', $command->getDescription());
    }

    public function testDefaultCommand()
    {
        $apl = new Application();
        $apl->setDefaultCommand(Php8Command::getDefaultName());
        $property = new \ReflectionProperty($apl, 'defaultCommand');

        $this->assertEquals('foo', $property->getValue($apl));

        $apl->setDefaultCommand(Php8Command2::getDefaultName());
        $property = new \ReflectionProperty($apl, 'defaultCommand');

        $this->assertEquals('foo2', $property->getValue($apl));
    }
}

// In order to get an unbound closure, we should create it outside a class
// scope.
function createClosure()
{
    return function (InputInterface $input, OutputInterface $output) {
        $output->writeln($this instanceof Command ? 'bound to the command' : 'not bound to the command');
    };
}

#[AsCommand(name: 'foo', description: 'desc', hidden: true, aliases: ['f'])]
class Php8Command extends Command
{
}

#[AsCommand(name: 'foo2', description: 'desc2', hidden: true)]
class Php8Command2 extends Command
{
}

#[AsCommand(name: 'my:command', description: 'This is a command I wrote all by myself')]
class MyAnnotatedCommand extends Command
{
    protected static $defaultName = 'i-shall-be-ignored';

    protected static $defaultDescription = 'This description should be ignored.';
}
