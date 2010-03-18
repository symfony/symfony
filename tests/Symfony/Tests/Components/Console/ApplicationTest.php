<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Console;

require_once __DIR__.'/../../bootstrap.php';

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
    self::$fixturesPath = realpath(__DIR__.'/../../../../fixtures/Symfony/Components/Console/');
    require_once self::$fixturesPath.'/FooCommand.php';
    require_once self::$fixturesPath.'/Foo1Command.php';
    require_once self::$fixturesPath.'/Foo2Command.php';
  }

  public function testConstructor()
  {
    $application = new Application('foo', 'bar');
    $this->assertEquals($application->getName(), 'foo', '__construct() takes the application name as its first argument');
    $this->assertEquals($application->getVersion(), 'bar', '__construct() takes the application version as its first argument');
    $this->assertEquals(array_keys($application->getCommands()), array('help', 'list'), '__construct() registered the help and list commands by default');
  }

  public function testSetGetName()
  {
    $application = new Application();
    $application->setName('foo');
    $this->assertEquals($application->getName(), 'foo', '->setName() sets the name of the application');
  }

  public function testSetGetVersion()
  {
    $application = new Application();
    $application->setVersion('bar');
    $this->assertEquals($application->getVersion(), 'bar', '->setVersion() sets the version of the application');
  }

  public function testGetLongVersion()
  {
    $application = new Application('foo', 'bar');
    $this->assertEquals($application->getLongVersion(), '<info>foo</info> version <comment>bar</comment>', '->getLongVersion() returns the long version of the application');
  }

  public function testHelp()
  {
    $application = new Application();
    $this->assertEquals($application->getHelp(), file_get_contents(self::$fixturesPath.'/application_gethelp.txt'), '->setHelp() returns a help message');
  }

  public function testGetCommands()
  {
    $application = new Application();
    $commands = $application->getCommands();
    $this->assertEquals(get_class($commands['help']), 'Symfony\\Components\\Console\\Command\\HelpCommand', '->getCommands() returns the registered commands');

    $application->addCommand(new \FooCommand());
    $commands = $application->getCommands('foo');
    $this->assertEquals(count($commands), 1, '->getCommands() takes a namespace as its first argument');
  }

  public function testRegister()
  {
    $application = new Application();
    $command = $application->register('foo');
    $this->assertEquals($command->getName(), 'foo', '->register() regiters a new command');
  }

  public function testAddCommand()
  {
    $application = new Application();
    $application->addCommand($foo = new \FooCommand());
    $commands = $application->getCommands();
    $this->assertEquals($commands['foo:bar'], $foo, '->addCommand() registers a command');

    $application = new Application();
    $application->addCommands(array($foo = new \FooCommand(), $foo1 = new \Foo1Command()));
    $commands = $application->getCommands();
    $this->assertEquals(array($commands['foo:bar'], $commands['foo:bar1']), array($foo, $foo1), '->addCommands() registers an array of commands');
  }

  public function testHasGetCommand()
  {
    $application = new Application();
    $this->assertTrue($application->hasCommand('list'), '->hasCommand() returns true if a named command is registered');
    $this->assertTrue(!$application->hasCommand('afoobar'), '->hasCommand() returns false if a named command is not registered');

    $application->addCommand($foo = new \FooCommand());
    $this->assertTrue($application->hasCommand('afoobar'), '->hasCommand() returns true if an alias is registered');
    $this->assertEquals($application->getCommand('foo:bar'), $foo, '->getCommand() returns a command by name');
    $this->assertEquals($application->getCommand('afoobar'), $foo, '->getCommand() returns a command by alias');

    try
    {
      $application->getCommand('foofoo');
      $this->fail('->getCommand() throws an \InvalidArgumentException if the command does not exist');
    }
    catch (\InvalidArgumentException $e)
    {
    }

    $application = new TestApplication();
    $application->addCommand($foo = new \FooCommand());
    $application->setWantHelps();
    $command = $application->getCommand('foo:bar');
    $this->assertEquals(get_class($command), 'Symfony\Components\Console\Command\HelpCommand', '->getCommand() returns the help command if --help is provided as the input');
  }

  public function testGetNamespaces()
  {
    $application = new TestApplication();
    $application->addCommand(new \FooCommand());
    $application->addCommand(new \Foo1Command());
    $this->assertEquals($application->getNamespaces(), array('foo'), '->getNamespaces() returns an array of unique used namespaces');
  }

  public function testFindNamespace()
  {
    $application = new TestApplication();
    $application->addCommand(new \FooCommand());
    $this->assertEquals($application->findNamespace('foo'), 'foo', '->findNamespace() returns the given namespace if it exists');
    $this->assertEquals($application->findNamespace('f'), 'foo', '->findNamespace() finds a namespace given an abbreviation');
    $application->addCommand(new \Foo2Command());
    $this->assertEquals($application->findNamespace('foo'), 'foo', '->findNamespace() returns the given namespace if it exists');
    try
    {
      $application->findNamespace('f');
      $this->fail('->findNamespace() throws an \InvalidArgumentException if the abbreviation is ambiguous');
    }
    catch (\InvalidArgumentException $e)
    {
    }

    try
    {
      $application->findNamespace('bar');
      $this->fail('->findNamespace() throws an \InvalidArgumentException if no command is in the given namespace');
    }
    catch (\InvalidArgumentException $e)
    {
    }
  }

  public function testFindCommand()
  {
    $application = new TestApplication();
    $application->addCommand(new \FooCommand());
    $this->assertEquals(get_class($application->findCommand('foo:bar')), 'FooCommand', '->findCommand() returns a command if its name exists');
    $this->assertEquals(get_class($application->findCommand('h')), 'Symfony\Components\Console\Command\HelpCommand', '->findCommand() returns a command if its name exists');
    $this->assertEquals(get_class($application->findCommand('f:bar')), 'FooCommand', '->findCommand() returns a command if the abbreviation for the namespace exists');
    $this->assertEquals(get_class($application->findCommand('f:b')), 'FooCommand', '->findCommand() returns a command if the abbreviation for the namespace and the command name exist');
    $this->assertEquals(get_class($application->findCommand('a')), 'FooCommand', '->findCommand() returns a command if the abbreviation exists for an alias');

    $application->addCommand(new \Foo1Command());
    $application->addCommand(new \Foo2Command());

    try
    {
      $application->findCommand('f');
      $this->fail('->findCommand() throws an \InvalidArgumentException if the abbreviation is ambiguous for a namespace');
    }
    catch (\InvalidArgumentException $e)
    {
    }

    try
    {
      $application->findCommand('a');
      $this->fail('->findCommand() throws an \InvalidArgumentException if the abbreviation is ambiguous for an alias');
    }
    catch (\InvalidArgumentException $e)
    {
    }

    try
    {
      $application->findCommand('foo:b');
      $this->fail('->findCommand() throws an \InvalidArgumentException if the abbreviation is ambiguous for a command');
    }
    catch (\InvalidArgumentException $e)
    {
    }
  }

  public function testSetCatchExceptions()
  {
    $application = new Application();
    $application->setAutoExit(false);
    $tester = new ApplicationTester($application);

    $application->setCatchExceptions(true);
    $tester->run(array('command' => 'foo'));
    $this->assertEquals($tester->getDisplay(), file_get_contents(self::$fixturesPath.'/application_renderexception1.txt'), '->setCatchExceptions() sets the catch exception flag');

    $application->setCatchExceptions(false);
    try
    {
      $tester->run(array('command' => 'foo'));
      $this->fail('->setCatchExceptions() sets the catch exception flag');
    }
    catch (\Exception $e)
    {
    }
  }

  public function testAsText()
  {
    $application = new Application();
    $application->addCommand(new \FooCommand);
    $this->assertEquals($application->asText(), file_get_contents(self::$fixturesPath.'/application_astext1.txt'), '->asText() returns a text representation of the application');
    $this->assertEquals($application->asText('foo'), file_get_contents(self::$fixturesPath.'/application_astext2.txt'), '->asText() returns a text representation of the application');
  }

  public function testAsXml()
  {
    $application = new Application();
    $application->addCommand(new \FooCommand);
    $this->assertEquals($application->asXml(), file_get_contents(self::$fixturesPath.'/application_asxml1.txt'), '->asXml() returns an XML representation of the application');
    $this->assertEquals($application->asXml('foo'), file_get_contents(self::$fixturesPath.'/application_asxml2.txt'), '->asXml() returns an XML representation of the application');
  }

  public function testRenderException()
  {
    $application = new Application();
    $application->setAutoExit(false);
    $tester = new ApplicationTester($application);

    $tester->run(array('command' => 'foo'));
    $this->assertEquals($tester->getDisplay(), file_get_contents(self::$fixturesPath.'/application_renderexception1.txt'), '->renderException() renders a pretty exception');

    $tester->run(array('command' => 'foo'), array('verbosity' => Output::VERBOSITY_VERBOSE));
    $this->assertRegExp('/Exception trace/', $tester->getDisplay(), '->renderException() renders a pretty exception with a stack trace when verbosity is verbose');

    $tester->run(array('command' => 'list', '--foo' => true));
    $this->assertEquals($tester->getDisplay(), file_get_contents(self::$fixturesPath.'/application_renderexception2.txt'), '->renderException() renders the command synopsis when an exception occurs in the context of a command');
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

    $this->assertEquals(get_class($command->input), 'Symfony\Components\Console\Input\ArgvInput', '->run() creates an ArgvInput by default if none is given');
    $this->assertEquals(get_class($command->output), 'Symfony\Components\Console\Output\ConsoleOutput', '->run() creates a ConsoleOutput by default if none is given');

    $application = new Application();
    $application->setAutoExit(false);
    $application->setCatchExceptions(false);
    $tester = new ApplicationTester($application);
    $tester->run(array());
    $this->assertEquals($tester->getDisplay(), file_get_contents(self::$fixturesPath.'/application_run1.txt'), '->run() runs the list command if no argument is passed');

    $tester->run(array('--help' => true));
    $this->assertEquals($tester->getDisplay(), file_get_contents(self::$fixturesPath.'/application_run2.txt'), '->run() runs the help command if --help is passed');

    $application = new Application();
    $application->setAutoExit(false);
    $application->setCatchExceptions(false);
    $tester = new ApplicationTester($application);
    $tester->run(array('command' => 'list', '--help' => true));
    $this->assertEquals($tester->getDisplay(), file_get_contents(self::$fixturesPath.'/application_run3.txt'), '->run() displays the help if --help is passed');

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
    $this->assertEquals($tester->getDisplay(), file_get_contents(self::$fixturesPath.'/application_run4.txt'), '->run() displays the program version if --version is passed');

    $application = new Application();
    $application->setAutoExit(false);
    $application->setCatchExceptions(false);
    $tester = new ApplicationTester($application);
    $tester->run(array('command' => 'list', '--quiet' => true));
    $this->assertEquals($tester->getDisplay(), '', '->run() removes all output if --quiet is passed');

    $application = new Application();
    $application->setAutoExit(false);
    $application->setCatchExceptions(false);
    $tester = new ApplicationTester($application);
    $tester->run(array('command' => 'list', '--verbose' => true));
    $this->assertEquals($tester->getOutput()->getVerbosity(), Output::VERBOSITY_VERBOSE, '->run() sets the output to verbose is --verbose is passed');

    $application = new Application();
    $application->setAutoExit(false);
    $application->setCatchExceptions(false);
    $application->addCommand(new \FooCommand());
    $tester = new ApplicationTester($application);
    $tester->run(array('command' => 'foo:bar', '--no-interaction' => true));
    $this->assertEquals($tester->getDisplay(), "called\n", '->run() does not called interact() if --no-interaction is passed');
  }
}

class TestApplication extends Application
{
  public function setWantHelps()
  {
    $this->wantHelps = true;
  }
}
