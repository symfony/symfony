<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\CLI\Task\Task;
use Symfony\Components\CLI\Application;
use Symfony\Components\CLI\Input\Definition;
use Symfony\Components\CLI\Input\Argument;
use Symfony\Components\CLI\Input\Option;
use Symfony\Components\CLI\Input\InputInterface;
use Symfony\Components\CLI\Input\StringInput;
use Symfony\Components\CLI\Output\OutputInterface;
use Symfony\Components\CLI\Output\NullOutput;
use Symfony\Components\CLI\Output\StreamOutput;
use Symfony\Components\CLI\Tester\TaskTester;

$fixtures = __DIR__.'/../../../../../fixtures/Symfony/Components/CLI';

$t = new LimeTest(46);

require_once $fixtures.'/TestTask.php';

$application = new Application();

// __construct()
$t->diag('__construct()');
try
{
  $task = new Task();
  $t->fail('__construct() throws a \LogicException if the name is null');
}
catch (\LogicException $e)
{
  $t->pass('__construct() throws a \LogicException if the name is null');
}
$task = new Task('foo:bar');
$t->is($task->getFullName(), 'foo:bar', '__construct() takes the task name as its first argument');

// ->setApplication()
$t->diag('->setApplication()');
$task = new TestTask();
$task->setApplication($application);
$t->is($task->getApplication(), $application, '->setApplication() sets the current application');

// ->setDefinition()
$t->diag('->setDefinition()');
$ret = $task->setDefinition($definition = new Definition());
$t->is($ret, $task, '->setDefinition() implements a fluent interface');
$t->is($task->getDefinition(), $definition, '->setDefinition() sets the current Definition instance');
$task->setDefinition(array(new Argument('foo'), new Option('bar')));
$t->ok($task->getDefinition()->hasArgument('foo'), '->setDefinition() also takes an array of Arguments and Options as an argument');
$t->ok($task->getDefinition()->hasOption('bar'), '->setDefinition() also takes an array of Arguments and Options as an argument');
$task->setDefinition(new Definition());

// ->addArgument()
$t->diag('->addArgument()');
$ret = $task->addArgument('foo');
$t->is($ret, $task, '->addArgument() implements a fluent interface');
$t->ok($task->getDefinition()->hasArgument('foo'), '->addArgument() adds an argument to the task');

// ->addOption()
$t->diag('->addOption()');
$ret = $task->addOption('foo');
$t->is($ret, $task, '->addOption() implements a fluent interface');
$t->ok($task->getDefinition()->hasOption('foo'), '->addOption() adds an option to the task');

// ->getNamespace() ->getName() ->getFullName() ->setName()
$t->diag('->getNamespace() ->getName() ->getFullName()');
$t->is($task->getNamespace(), 'namespace', '->getNamespace() returns the task namespace');
$t->is($task->getName(), 'name', '->getName() returns the task name');
$t->is($task->getFullName(), 'namespace:name', '->getNamespace() returns the full task name');
$task->setName('foo');
$t->is($task->getName(), 'foo', '->setName() sets the task name');

$task->setName(':bar');
$t->is($task->getName(), 'bar', '->setName() sets the task name');
$t->is($task->getNamespace(), '', '->setName() can set the task namespace');

$ret = $task->setName('foobar:bar');
$t->is($ret, $task, '->setName() implements a fluent interface');
$t->is($task->getName(), 'bar', '->setName() sets the task name');
$t->is($task->getNamespace(), 'foobar', '->setName() can set the task namespace');

try
{
  $task->setName('');
  $t->fail('->setName() throws an \InvalidArgumentException if the name is empty');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->setName() throws an \InvalidArgumentException if the name is empty');
}

try
{
  $task->setName('foo:');
  $t->fail('->setName() throws an \InvalidArgumentException if the name is empty');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->setName() throws an \InvalidArgumentException if the name is empty');
}

// ->getDescription() ->setDescription()
$t->diag('->getDescription() ->setDescription()');
$t->is($task->getDescription(), 'description', '->getDescription() returns the description');
$ret = $task->setDescription('description1');
$t->is($ret, $task, '->setDescription() implements a fluent interface');
$t->is($task->getDescription(), 'description1', '->setDescription() sets the description');

// ->getHelp() ->setHelp()
$t->diag('->getHelp() ->setHelp()');
$t->is($task->getHelp(), 'help', '->getHelp() returns the help');
$ret = $task->setHelp('help1');
$t->is($ret, $task, '->setHelp() implements a fluent interface');
$t->is($task->getHelp(), 'help1', '->setHelp() sets the help');

// ->getAliases() ->setAliases()
$t->diag('->getAliases() ->setAliases()');
$t->is($task->getAliases(), array('name'), '->getAliases() returns the aliases');
$ret = $task->setAliases(array('name1'));
$t->is($ret, $task, '->setAliases() implements a fluent interface');
$t->is($task->getAliases(), array('name1'), '->setAliases() sets the aliases');

// ->getSynopsis()
$t->diag('->getSynopsis()');
$t->is($task->getSynopsis(), '%s foobar:bar [--foo] [foo]', '->getSynopsis() returns the synopsis');

// ->mergeApplicationDefinition()
$t->diag('->mergeApplicationDefinition()');
$application1 = new Application();
$application1->getDefinition()->addArguments(array(new Argument('foo')));
$application1->getDefinition()->addOptions(array(new Option('bar')));
$task = new TestTask();
$task->setApplication($application1);
$task->setDefinition($definition = new Definition(array(new Argument('bar'), new Option('foo'))));
$task->mergeApplicationDefinition();
$t->ok($task->getDefinition()->hasArgument('foo'), '->mergeApplicationDefinition() merges the application arguments and the task arguments');
$t->ok($task->getDefinition()->hasArgument('bar'), '->mergeApplicationDefinition() merges the application arguments and the task arguments');
$t->ok($task->getDefinition()->hasOption('foo'), '->mergeApplicationDefinition() merges the application options and the task options');
$t->ok($task->getDefinition()->hasOption('bar'), '->mergeApplicationDefinition() merges the application options and the task options');

$task->mergeApplicationDefinition();
$t->is($task->getDefinition()->getArgumentCount(), 3, '->mergeApplicationDefinition() does not try to merge twice the application arguments and options');

$task = new TestTask();
$task->mergeApplicationDefinition();
$t->pass('->mergeApplicationDefinition() does nothing if application is not set');

// ->run()
$t->diag('->run()');
$task = new TestTask();
$task->setApplication($application);
$tester = new TaskTester($task);
try
{
  $tester->execute(array('--bar' => true));
  $t->fail('->run() throws a \RuntimeException when the input does not validate the current Definition');
}
catch (\RuntimeException $e)
{
  $t->pass('->run() throws a \RuntimeException when the input does not validate the current Definition');
}

$t->is($tester->execute(array(), array('interactive' => true)), "interact called\nexecute called\n", '->run() calls the interact() method if the input is interactive');
$t->is($tester->execute(array(), array('interactive' => false)), "execute called\n", '->run() does not call the interact() method if the input is not interactive');

$task = new Task('foo');
try
{
  $task->run(new StringInput(''), new NullOutput());
  $t->fail('->run() throws a \LogicException if the execute() method has not been overriden and no code has been provided');
}
catch (\LogicException $e)
{
  $t->pass('->run() throws a \LogicException if the execute() method has not been overriden and no code has been provided');
}

// ->setCode()
$t->diag('->setCode()');
$task = new TestTask();
$task->setApplication($application);
$ret = $task->setCode(function (InputInterface $input, OutputInterface $output)
{
  $output->write('from the code...');
});
$t->is($ret, $task, '->setCode() implements a fluent interface');
$tester = new TaskTester($task);
$tester->execute(array());
$t->is($tester->getDisplay(), "interact called\nfrom the code...\n");

// ->asText()
$t->diag('->asText()');
$t->is($task->asText(), file_get_contents($fixtures.'/task_astext.txt'), '->asText() returns a text representation of the task');

// ->asXml()
$t->diag('->asXml()');
$t->is($task->asXml(), file_get_contents($fixtures.'/task_asxml.txt'), '->asXml() returns an XML representation of the task');
