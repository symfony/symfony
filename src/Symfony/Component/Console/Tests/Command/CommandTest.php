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
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Tester\CommandTester;

class CommandTest extends \PHPUnit_Framework_TestCase
{
    protected static $fixturesPath;

    public static function setUpBeforeClass()
    {
        self::$fixturesPath = __DIR__.'/../Fixtures/';
        require_once self::$fixturesPath.'/TestCommand.php';
    }

    public function testConstructor()
    {
        $command = new Command('foo:bar');
        $this->assertEquals('foo:bar', $command->getName(), '__construct() takes the command name as its first argument');
    }

    /**
     * @expectedException        \LogicException
     * @expectedExceptionMessage The command defined in "Symfony\Component\Console\Command\Command" cannot have an empty name.
     */
    public function testCommandNameCannotBeEmpty()
    {
        new Command();
    }

    public function testSetApplication()
    {
        $application = new Application();
        $command = new \TestCommand();
        $command->setApplication($application);
        $this->assertEquals($application, $command->getApplication(), '->setApplication() sets the current application');
    }

    public function testSetGetDefinition()
    {
        $command = new \TestCommand();
        $ret = $command->setDefinition($definition = new InputDefinition());
        $this->assertEquals($command, $ret, '->setDefinition() implements a fluent interface');
        $this->assertEquals($definition, $command->getDefinition(), '->setDefinition() sets the current InputDefinition instance');
        $command->setDefinition(array(new InputArgument('foo'), new InputOption('bar')));
        $this->assertTrue($command->getDefinition()->hasArgument('foo'), '->setDefinition() also takes an array of InputArguments and InputOptions as an argument');
        $this->assertTrue($command->getDefinition()->hasOption('bar'), '->setDefinition() also takes an array of InputArguments and InputOptions as an argument');
        $command->setDefinition(new InputDefinition());
    }

    public function testGetHelper()
    {
        $application = new Application();
        $command = new \TestCommand();
        $command->setApplication($application);
        $formatterHelper = new FormatterHelper();
        $this->assertEquals($formatterHelper->getName(), $command->getHelper('formatter')->getName(), '->getHelper() returns the correct helper');
    }

    public function testGet()
    {
        $application = new Application();
        $command = new \TestCommand();
        $command->setApplication($application);
        $formatterHelper = new FormatterHelper();
        $this->assertEquals($formatterHelper->getName(), $command->getHelper('formatter')->getName(), '->__get() returns the correct helper');
    }

    public function testMergeApplicationDefinition()
    {
        $application1 = new Application();
        $application1->getDefinition()->addArguments(array(new InputArgument('foo')));
        $application1->getDefinition()->addOptions(array(new InputOption('bar')));
        $command = new \TestCommand();
        $command->setApplication($application1);
        $command->setDefinition($definition = new InputDefinition(array(new InputArgument('bar'), new InputOption('foo'))));

        $r = new \ReflectionObject($command);
        $m = $r->getMethod('mergeApplicationDefinition');
        $m->setAccessible(true);
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
        $application1->getDefinition()->addArguments(array(new InputArgument('foo')));
        $application1->getDefinition()->addOptions(array(new InputOption('bar')));
        $command = new \TestCommand();
        $command->setApplication($application1);
        $command->setDefinition($definition = new InputDefinition(array()));

        $r = new \ReflectionObject($command);
        $m = $r->getMethod('mergeApplicationDefinition');
        $m->setAccessible(true);
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

        $tester->execute(array(), array('interactive' => true));

        $this->assertEquals('interact called'.PHP_EOL.'execute called'.PHP_EOL, $tester->getDisplay(), '->run() calls the interact() method if the input is interactive');
    }

    public function testRunNonInteractive()
    {
        $tester = new CommandTester(new \TestCommand());

        $tester->execute(array(), array('interactive' => false));

        $this->assertEquals('execute called'.PHP_EOL, $tester->getDisplay(), '->run() does not call the interact() method if the input is not interactive');
    }

    /**
     * @expectedException        \LogicException
     * @expectedExceptionMessage You must override the execute() method in the concrete command class.
     */
    public function testExecuteMethodNeedsToBeOverridden()
    {
        $command = new Command('foo');
        $command->run(new StringInput(''), new NullOutput());
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage The "--bar" option does not exist.
     */
    public function testRunWithInvalidOption()
    {
        $command = new \TestCommand();
        $tester = new CommandTester($command);
        $tester->execute(array('--bar' => true));
    }

    public function testRunReturnsIntegerExitCode()
    {
        $command = new \TestCommand();
        $exitCode = $command->run(new StringInput(''), new NullOutput());
        $this->assertSame(0, $exitCode, '->run() returns integer exit code (treats null as 0)');

        $command = $this->getMock('TestCommand', array('execute'));
        $command->expects($this->once())
             ->method('execute')
             ->will($this->returnValue('2.3'));
        $exitCode = $command->run(new StringInput(''), new NullOutput());
        $this->assertSame(2, $exitCode, '->run() returns integer exit code (casts numeric to int)');
    }

    public function testRunReturnsAlwaysInteger()
    {
        $command = new \TestCommand();

        $this->assertSame(0, $command->run(new StringInput(''), new NullOutput()));
    }

    public function testSetCode()
    {
        $command = new \TestCommand();
        $ret = $command->setCode(function (InputInterface $input, OutputInterface $output) {
            $output->writeln('from the code...');
        });
        $this->assertEquals($command, $ret, '->setCode() implements a fluent interface');
        $tester = new CommandTester($command);
        $tester->execute(array());
        $this->assertEquals('interact called'.PHP_EOL.'from the code...'.PHP_EOL, $tester->getDisplay());
    }

    public function testSetCodeWithNonClosureCallable()
    {
        $command = new \TestCommand();
        $ret = $command->setCode(array($this, 'callableMethodCommand'));
        $this->assertEquals($command, $ret, '->setCode() implements a fluent interface');
        $tester = new CommandTester($command);
        $tester->execute(array());
        $this->assertEquals('interact called'.PHP_EOL.'from the code...'.PHP_EOL, $tester->getDisplay());
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage Invalid callable provided to Command::setCode.
     */
    public function testSetCodeWithNonCallable()
    {
        $command = new \TestCommand();
        $command->setCode(array($this, 'nonExistentMethod'));
    }

    public function callableMethodCommand(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('from the code...');
    }

    /**
     * @group legacy
     */
    public function testLegacyAsText()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $command = new \TestCommand();
        $command->setApplication(new Application());
        $tester = new CommandTester($command);
        $tester->execute(array('command' => $command->getName()));
        $this->assertStringEqualsFile(self::$fixturesPath.'/command_astext.txt', $command->asText(), '->asText() returns a text representation of the command');
    }

    /**
     * @group legacy
     */
    public function testLegacyAsXml()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $command = new \TestCommand();
        $command->setApplication(new Application());
        $tester = new CommandTester($command);
        $tester->execute(array('command' => $command->getName()));
        $this->assertXmlStringEqualsXmlFile(self::$fixturesPath.'/command_asxml.txt', $command->asXml(), '->asXml() returns an XML representation of the command');
    }

    public function testSetConfiguration()
    {
        $configuration = new CommandConfiguration();
        $configuration
            ->setName('foo:bar')
            ->setAliases(array('name'))
            ->setDescription('description')
            ->setHelp('help');

        // Via constructor
        $command = new Command(null, $configuration);
        $this->assertEquals('foo:bar', $command->getName());
        $this->assertEquals(array('name'), $command->getAliases());
        $this->assertEquals('description', $command->getDescription());
        $this->assertEquals('help', $command->getHelp());

        // Via setter
        $command = new Command('foo');
        $command->setConfiguration($configuration);
        $this->assertEquals('foo:bar', $command->getName());
        $this->assertEquals(array('name'), $command->getAliases());
        $this->assertEquals('description', $command->getDescription());
        $this->assertEquals('help', $command->getHelp());
    }
}
