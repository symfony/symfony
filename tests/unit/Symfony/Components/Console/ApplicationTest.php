<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\Console\Application;
use Symfony\Components\Console\Input\ArrayInput;
use Symfony\Components\Console\Output\Output;
use Symfony\Components\Console\Output\StreamOutput;
use Symfony\Components\Console\Tester\ApplicationTester;

$fixtures = __DIR__.'/../../../../fixtures/Symfony/Components/Console';

require_once $fixtures.'/FooCommand.php';
require_once $fixtures.'/Foo1Command.php';
require_once $fixtures.'/Foo2Command.php';

$t = new LimeTest(52);

// __construct()
$t->diag('__construct()');
$application = new Application('foo', 'bar');
$t->is($application->getName(), 'foo', '__construct() takes the application name as its first argument');
$t->is($application->getVersion(), 'bar', '__construct() takes the application version as its first argument');
$t->is(array_keys($application->getCommands()), array('help', 'list'), '__construct() registered the help and list commands by default');

// ->setName() ->getName()
$t->diag('->setName() ->getName()');
$application = new Application();
$application->setName('foo');
$t->is($application->getName(), 'foo', '->setName() sets the name of the application');

// ->getVersion() ->getVersion()
$t->diag('->getVersion() ->getVersion()');
$application = new Application();
$application->setVersion('bar');
$t->is($application->getVersion(), 'bar', '->setVersion() sets the version of the application');

// ->getLongVersion()
$t->diag('->getLongVersion()');
$application = new Application('foo', 'bar');
$t->is($application->getLongVersion(), '<info>foo</info> version <comment>bar</comment>', '->getLongVersion() returns the long version of the application');

// ->getHelp()
$t->diag('->getHelp()');
$application = new Application();
$t->is($application->getHelp(), file_get_contents($fixtures.'/application_gethelp.txt'), '->setHelp() returns a help message');

// ->getCommands()
$t->diag('->getCommands()');
$application = new Application();
$commands = $application->getCommands();
$t->is(get_class($commands['help']), 'Symfony\\Components\\Console\\Command\\HelpCommand', '->getCommands() returns the registered commands');

$application->addCommand(new FooCommand());
$commands = $application->getCommands('foo');
$t->is(count($commands), 1, '->getCommands() takes a namespace as its first argument');

// ->register()
$t->diag('->register()');
$application = new Application();
$command = $application->register('foo');
$t->is($command->getName(), 'foo', '->register() regiters a new command');

// ->addCommand() ->addCommands()
$t->diag('->addCommand() ->addCommands()');
$application = new Application();
$application->addCommand($foo = new FooCommand());
$commands = $application->getCommands();
$t->is($commands['foo:bar'], $foo, '->addCommand() registers a command');

$application = new Application();
$application->addCommands(array($foo = new FooCommand(), $foo1 = new Foo1Command()));
$commands = $application->getCommands();
$t->is(array($commands['foo:bar'], $commands['foo:bar1']), array($foo, $foo1), '->addCommands() registers an array of commands');

// ->hasCommand() ->getCommand()
$t->diag('->hasCommand() ->getCommand()');
$application = new Application();
$t->ok($application->hasCommand('list'), '->hasCommand() returns true if a named command is registered');
$t->ok(!$application->hasCommand('afoobar'), '->hasCommand() returns false if a named command is not registered');

$application->addCommand($foo = new FooCommand());
$t->ok($application->hasCommand('afoobar'), '->hasCommand() returns true if an alias is registered');
$t->is($application->getCommand('foo:bar'), $foo, '->getCommand() returns a command by name');
$t->is($application->getCommand('afoobar'), $foo, '->getCommand() returns a command by alias');

try
{
  $application->getCommand('foofoo');
  $t->fail('->getCommand() throws an \InvalidArgumentException if the command does not exist');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->getCommand() throws an \InvalidArgumentException if the command does not exist');
}

class TestApplication extends Application
{
  public function setWantHelps()
  {
    $this->wantHelps = true;
  }
}
$application = new TestApplication();
$application->addCommand($foo = new FooCommand());
$application->setWantHelps();
$command = $application->getCommand('foo:bar');
$t->is(get_class($command), 'Symfony\Components\Console\Command\HelpCommand', '->getCommand() returns the help command if --help is provided as the input');

// ->getNamespaces()
$t->diag('->getNamespaces()');
$application = new TestApplication();
$application->addCommand(new FooCommand());
$application->addCommand(new Foo1Command());
$t->is($application->getNamespaces(), array('foo'), '->getNamespaces() returns an array of unique used namespaces');

// ->findNamespace()
$t->diag('->findNamespace()');
$application = new TestApplication();
$application->addCommand(new FooCommand());
$t->is($application->findNamespace('foo'), 'foo', '->findNamespace() returns the given namespace if it exists');
$t->is($application->findNamespace('f'), 'foo', '->findNamespace() finds a namespace given an abbreviation');
$application->addCommand(new Foo2Command());
$t->is($application->findNamespace('foo'), 'foo', '->findNamespace() returns the given namespace if it exists');
try
{
  $application->findNamespace('f');
  $t->fail('->findNamespace() throws an \InvalidArgumentException if the abbreviation is ambiguous');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->findNamespace() throws an \InvalidArgumentException if the abbreviation is ambiguous');
}

try
{
  $application->findNamespace('bar');
  $t->fail('->findNamespace() throws an \InvalidArgumentException if no command is in the given namespace');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->findNamespace() throws an \InvalidArgumentException if no command is in the given namespace');
}

// ->findCommand()
$t->diag('->findCommand()');
$application = new TestApplication();
$application->addCommand(new FooCommand());
$t->is(get_class($application->findCommand('foo:bar')), 'FooCommand', '->findCommand() returns a command if its name exists');
$t->is(get_class($application->findCommand('h')), 'Symfony\Components\Console\Command\HelpCommand', '->findCommand() returns a command if its name exists');
$t->is(get_class($application->findCommand('f:bar')), 'FooCommand', '->findCommand() returns a command if the abbreviation for the namespace exists');
$t->is(get_class($application->findCommand('f:b')), 'FooCommand', '->findCommand() returns a command if the abbreviation for the namespace and the command name exist');
$t->is(get_class($application->findCommand('a')), 'FooCommand', '->findCommand() returns a command if the abbreviation exists for an alias');

$application->addCommand(new Foo1Command());
$application->addCommand(new Foo2Command());

try
{
  $application->findCommand('f');
  $t->fail('->findCommand() throws an \InvalidArgumentException if the abbreviation is ambiguous for a namespace');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->findCommand() throws an \InvalidArgumentException if the abbreviation is ambiguous for a namespace');
}

try
{
  $application->findCommand('a');
  $t->fail('->findCommand() throws an \InvalidArgumentException if the abbreviation is ambiguous for an alias');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->findCommand() throws an \InvalidArgumentException if the abbreviation is ambiguous for an alias');
}

try
{
  $application->findCommand('foo:b');
  $t->fail('->findCommand() throws an \InvalidArgumentException if the abbreviation is ambiguous for a command');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->findCommand() throws an \InvalidArgumentException if the abbreviation is ambiguous for a taks');
}

// ->setCatchExceptions()
$t->diag('->setCatchExceptions()');
$application = new Application();
$application->setAutoExit(false);
$tester = new ApplicationTester($application);

$application->setCatchExceptions(true);
$tester->run(array('command' => 'foo'));
$t->is($tester->getDisplay(), file_get_contents($fixtures.'/application_renderexception1.txt'), '->setCatchExceptions() sets the catch exception flag');

$application->setCatchExceptions(false);
try
{
  $tester->run(array('command' => 'foo'));
  $t->fail('->setCatchExceptions() sets the catch exception flag');
}
catch (\Exception $e)
{
  $t->pass('->setCatchExceptions() sets the catch exception flag');
}

// ->asText()
$t->diag('->asText()');
$application = new Application();
$application->addCommand(new FooCommand);
$t->is($application->asText(), file_get_contents($fixtures.'/application_astext1.txt'), '->asText() returns a text representation of the application');
$t->is($application->asText('foo'), file_get_contents($fixtures.'/application_astext2.txt'), '->asText() returns a text representation of the application');

// ->asXml()
$t->diag('->asXml()');
$application = new Application();
$application->addCommand(new FooCommand);
$t->is($application->asXml(), file_get_contents($fixtures.'/application_asxml1.txt'), '->asXml() returns an XML representation of the application');
$t->is($application->asXml('foo'), file_get_contents($fixtures.'/application_asxml2.txt'), '->asXml() returns an XML representation of the application');

// ->renderException()
$t->diag('->renderException()');
$application = new Application();
$application->setAutoExit(false);
$tester = new ApplicationTester($application);

$tester->run(array('command' => 'foo'));
$t->is($tester->getDisplay(), file_get_contents($fixtures.'/application_renderexception1.txt'), '->renderException() renders a pretty exception');

$tester->run(array('command' => 'foo'), array('verbosity' => Output::VERBOSITY_VERBOSE));
$t->like($tester->getDisplay(), '/Exception trace/', '->renderException() renders a pretty exception with a stack trace when verbosity is verbose');

$tester->run(array('command' => 'list', '--foo' => true));
$t->is($tester->getDisplay(), file_get_contents($fixtures.'/application_renderexception2.txt'), '->renderException() renders the command synopsis when an exception occurs in the context of a command');

// ->run()
$t->diag('->run()');
$application = new Application();
$application->setAutoExit(false);
$application->setCatchExceptions(false);
$application->addCommand($command = new Foo1Command());
$_SERVER['argv'] = array('cli.php', 'foo:bar1');

ob_start();
$application->run();
ob_end_clean();

$t->is(get_class($command->input), 'Symfony\Components\Console\Input\ArgvInput', '->run() creates an ArgvInput by default if none is given');
$t->is(get_class($command->output), 'Symfony\Components\Console\Output\ConsoleOutput', '->run() creates a ConsoleOutput by default if none is given');

$application = new Application();
$application->setAutoExit(false);
$application->setCatchExceptions(false);
$tester = new ApplicationTester($application);
$tester->run(array());
$t->is($tester->getDisplay(), file_get_contents($fixtures.'/application_run1.txt'), '->run() runs the list command if no argument is passed');

$tester->run(array('--help' => true));
$t->is($tester->getDisplay(), file_get_contents($fixtures.'/application_run2.txt'), '->run() runs the help command if --help is passed');

$application = new Application();
$application->setAutoExit(false);
$application->setCatchExceptions(false);
$tester = new ApplicationTester($application);
$tester->run(array('command' => 'list', '--help' => true));
$t->is($tester->getDisplay(), file_get_contents($fixtures.'/application_run3.txt'), '->run() displays the help if --help is passed');

$application = new Application();
$application->setAutoExit(false);
$application->setCatchExceptions(false);
$tester = new ApplicationTester($application);
$tester->run(array('--color' => true));
$t->ok($tester->getOutput()->isDecorated(), '->run() forces color output if --color is passed');

$application = new Application();
$application->setAutoExit(false);
$application->setCatchExceptions(false);
$tester = new ApplicationTester($application);
$tester->run(array('--version' => true));
$t->is($tester->getDisplay(), file_get_contents($fixtures.'/application_run4.txt'), '->run() displays the program version if --version is passed');

$application = new Application();
$application->setAutoExit(false);
$application->setCatchExceptions(false);
$tester = new ApplicationTester($application);
$tester->run(array('command' => 'list', '--quiet' => true));
$t->is($tester->getDisplay(), '', '->run() removes all output if --quiet is passed');

$application = new Application();
$application->setAutoExit(false);
$application->setCatchExceptions(false);
$tester = new ApplicationTester($application);
$tester->run(array('command' => 'list', '--verbose' => true));
$t->is($tester->getOutput()->getVerbosity(), Output::VERBOSITY_VERBOSE, '->run() sets the output to verbose is --verbose is passed');

$application = new Application();
$application->setAutoExit(false);
$application->setCatchExceptions(false);
$application->addCommand(new FooCommand());
$tester = new ApplicationTester($application);
$tester->run(array('command' => 'foo:bar', '--no-interaction' => true));
$t->is($tester->getDisplay(), "called\n", '->run() does not called interact() if --no-interaction is passed');
