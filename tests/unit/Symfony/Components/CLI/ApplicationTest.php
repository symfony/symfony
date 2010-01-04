<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\CLI\Application;
use Symfony\Components\CLI\Input\ArrayInput;
use Symfony\Components\CLI\Output\Output;
use Symfony\Components\CLI\Output\StreamOutput;
use Symfony\Components\CLI\Tester\ApplicationTester;

$fixtures = __DIR__.'/../../../../fixtures/Symfony/Components/CLI';

require_once $fixtures.'/FooTask.php';
require_once $fixtures.'/Foo1Task.php';
require_once $fixtures.'/Foo2Task.php';

$t = new LimeTest(52);

// __construct()
$t->diag('__construct()');
$application = new Application('foo', 'bar');
$t->is($application->getName(), 'foo', '__construct() takes the application name as its first argument');
$t->is($application->getVersion(), 'bar', '__construct() takes the application version as its first argument');
$t->is(array_keys($application->getTasks()), array('help', 'list'), '__construct() registered the help and list tasks by default');

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

// ->getTasks()
$t->diag('->getTasks()');
$application = new Application();
$tasks = $application->getTasks();
$t->is(get_class($tasks['help']), 'Symfony\\Components\\CLI\\Task\\HelpTask', '->getTasks() returns the registered tasks');

$application->addTask(new FooTask());
$tasks = $application->getTasks('foo');
$t->is(count($tasks), 1, '->getTasks() takes a namespace as its first argument');

// ->register()
$t->diag('->register()');
$application = new Application();
$task = $application->register('foo');
$t->is($task->getName(), 'foo', '->register() regiters a new task');

// ->addTask() ->addTasks()
$t->diag('->addTask() ->addTasks()');
$application = new Application();
$application->addTask($foo = new FooTask());
$tasks = $application->getTasks();
$t->is($tasks['foo:bar'], $foo, '->addTask() registers a task');

$application = new Application();
$application->addTasks(array($foo = new FooTask(), $foo1 = new Foo1Task()));
$tasks = $application->getTasks();
$t->is(array($tasks['foo:bar'], $tasks['foo:bar1']), array($foo, $foo1), '->addTasks() registers an array of tasks');

// ->hasTask() ->getTask()
$t->diag('->hasTask() ->getTask()');
$application = new Application();
$t->ok($application->hasTask('list'), '->hasTask() returns true if a named task is registered');
$t->ok(!$application->hasTask('afoobar'), '->hasTask() returns false if a named task is not registered');

$application->addTask($foo = new FooTask());
$t->ok($application->hasTask('afoobar'), '->hasTask() returns true if an alias is registered');
$t->is($application->getTask('foo:bar'), $foo, '->getTask() returns a task by name');
$t->is($application->getTask('afoobar'), $foo, '->getTask() returns a task by alias');

try
{
  $application->getTask('foofoo');
  $t->fail('->getTask() throws an \InvalidArgumentException if the task does not exist');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->getTask() throws an \InvalidArgumentException if the task does not exist');
}

class TestApplication extends Application
{
  public function setWantHelps()
  {
    $this->wantHelps = true;
  }
}
$application = new TestApplication();
$application->addTask($foo = new FooTask());
$application->setWantHelps();
$task = $application->getTask('foo:bar');
$t->is(get_class($task), 'Symfony\Components\CLI\Task\HelpTask', '->getTask() returns the help task if --help is provided as the input');

// ->getNamespaces()
$t->diag('->getNamespaces()');
$application = new TestApplication();
$application->addTask(new FooTask());
$application->addTask(new Foo1Task());
$t->is($application->getNamespaces(), array('foo'), '->getNamespaces() returns an array of unique used namespaces');

// ->findNamespace()
$t->diag('->findNamespace()');
$application = new TestApplication();
$application->addTask(new FooTask());
$t->is($application->findNamespace('foo'), 'foo', '->findNamespace() returns the given namespace if it exists');
$t->is($application->findNamespace('f'), 'foo', '->findNamespace() finds a namespace given an abbreviation');
$application->addTask(new Foo2Task());
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
  $t->fail('->findNamespace() throws an \InvalidArgumentException if no task is in the given namespace');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->findNamespace() throws an \InvalidArgumentException if no task is in the given namespace');
}

// ->findTask()
$t->diag('->findTask()');
$application = new TestApplication();
$application->addTask(new FooTask());
$t->is(get_class($application->findTask('foo:bar')), 'FooTask', '->findTask() returns a task if its name exists');
$t->is(get_class($application->findTask('h')), 'Symfony\Components\CLI\Task\HelpTask', '->findTask() returns a task if its name exists');
$t->is(get_class($application->findTask('f:bar')), 'FooTask', '->findTask() returns a task if the abbreviation for the namespace exists');
$t->is(get_class($application->findTask('f:b')), 'FooTask', '->findTask() returns a task if the abbreviation for the namespace and the task name exist');
$t->is(get_class($application->findTask('a')), 'FooTask', '->findTask() returns a task if the abbreviation exists for an alias');

$application->addTask(new Foo1Task());
$application->addTask(new Foo2Task());

try
{
  $application->findTask('f');
  $t->fail('->findTask() throws an \InvalidArgumentException if the abbreviation is ambiguous for a namespace');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->findTask() throws an \InvalidArgumentException if the abbreviation is ambiguous for a namespace');
}

try
{
  $application->findTask('a');
  $t->fail('->findTask() throws an \InvalidArgumentException if the abbreviation is ambiguous for an alias');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->findTask() throws an \InvalidArgumentException if the abbreviation is ambiguous for an alias');
}

try
{
  $application->findTask('foo:b');
  $t->fail('->findTask() throws an \InvalidArgumentException if the abbreviation is ambiguous for a task');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->findTask() throws an \InvalidArgumentException if the abbreviation is ambiguous for a taks');
}

// ->setCatchExceptions()
$t->diag('->setCatchExceptions()');
$application = new Application();
$application->setAutoExit(false);
$tester = new ApplicationTester($application);

$application->setCatchExceptions(true);
$tester->run(array('task' => 'foo'));
$t->is($tester->getDisplay(), file_get_contents($fixtures.'/application_renderexception1.txt'), '->setCatchExceptions() sets the catch exception flag');

$application->setCatchExceptions(false);
try
{
  $tester->run(array('task' => 'foo'));
  $t->fail('->setCatchExceptions() sets the catch exception flag');
}
catch (\Exception $e)
{
  $t->pass('->setCatchExceptions() sets the catch exception flag');
}

// ->asText()
$t->diag('->asText()');
$application = new Application();
$application->addTask(new FooTask);
$t->is($application->asText(), file_get_contents($fixtures.'/application_astext1.txt'), '->asText() returns a text representation of the application');
$t->is($application->asText('foo'), file_get_contents($fixtures.'/application_astext2.txt'), '->asText() returns a text representation of the application');

// ->asXml()
$t->diag('->asXml()');
$application = new Application();
$application->addTask(new FooTask);
$t->is($application->asXml(), file_get_contents($fixtures.'/application_asxml1.txt'), '->asXml() returns an XML representation of the application');
$t->is($application->asXml('foo'), file_get_contents($fixtures.'/application_asxml2.txt'), '->asXml() returns an XML representation of the application');

// ->renderException()
$t->diag('->renderException()');
$application = new Application();
$application->setAutoExit(false);
$tester = new ApplicationTester($application);

$tester->run(array('task' => 'foo'));
$t->is($tester->getDisplay(), file_get_contents($fixtures.'/application_renderexception1.txt'), '->renderException() renders a pretty exception');

$tester->run(array('task' => 'foo'), array('verbosity' => Output::VERBOSITY_VERBOSE));
$t->like($tester->getDisplay(), '/Exception trace/', '->renderException() renders a pretty exception with a stack trace when verbosity is verbose');

$tester->run(array('task' => 'list', '--foo' => true));
$t->is($tester->getDisplay(), file_get_contents($fixtures.'/application_renderexception2.txt'), '->renderException() renders the task synopsis when an exception occurs in the context of a task');

// ->run()
$t->diag('->run()');
$application = new Application();
$application->setAutoExit(false);
$application->setCatchExceptions(false);
$application->addTask($task = new Foo1Task());
$_SERVER['argv'] = array('cli.php', 'foo:bar1');

ob_start();
$application->run();
ob_end_clean();

$t->is(get_class($task->input), 'Symfony\Components\CLI\Input\ArgvInput', '->run() creates an ArgvInput by default if none is given');
$t->is(get_class($task->output), 'Symfony\Components\CLI\Output\ConsoleOutput', '->run() creates a ConsoleOutput by default if none is given');

$application = new Application();
$application->setAutoExit(false);
$application->setCatchExceptions(false);
$tester = new ApplicationTester($application);
$tester->run(array());
$t->is($tester->getDisplay(), file_get_contents($fixtures.'/application_run1.txt'), '->run() runs the list task if no argument is passed');

$tester->run(array('--help' => true));
$t->is($tester->getDisplay(), file_get_contents($fixtures.'/application_run2.txt'), '->run() runs the help task if --help is passed');

$application = new Application();
$application->setAutoExit(false);
$application->setCatchExceptions(false);
$tester = new ApplicationTester($application);
$tester->run(array('task' => 'list', '--help' => true));
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
$tester->run(array('task' => 'list', '--quiet' => true));
$t->is($tester->getDisplay(), '', '->run() removes all output if --quiet is passed');

$application = new Application();
$application->setAutoExit(false);
$application->setCatchExceptions(false);
$tester = new ApplicationTester($application);
$tester->run(array('task' => 'list', '--verbose' => true));
$t->is($tester->getOutput()->getVerbosity(), Output::VERBOSITY_VERBOSE, '->run() sets the output to verbose is --verbose is passed');

$application = new Application();
$application->setAutoExit(false);
$application->setCatchExceptions(false);
$application->addTask(new FooTask());
$tester = new ApplicationTester($application);
$tester->run(array('task' => 'foo:bar', '--no-interaction' => true));
$t->is($tester->getDisplay(), "called\n", '->run() does not called interact() if --no-interaction is passed');
