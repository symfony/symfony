<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\Console\Command\Command;
use Symfony\Components\Console\Application;
use Symfony\Components\Console\Input\InputDefinition;
use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Input\StringInput;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\NullOutput;
use Symfony\Components\Console\Output\StreamOutput;
use Symfony\Components\Console\Tester\CommandTester;

$fixtures = __DIR__.'/../../../../../fixtures/Symfony/Components/Console';

$t = new LimeTest(46);

require_once $fixtures.'/TestCommand.php';

$application = new Application();

// __construct()
$t->diag('__construct()');
try
{
  $command = new Command();
  $t->fail('__construct() throws a \LogicException if the name is null');
}
catch (\LogicException $e)
{
  $t->pass('__construct() throws a \LogicException if the name is null');
}
$command = new Command('foo:bar');
$t->is($command->getFullName(), 'foo:bar', '__construct() takes the command name as its first argument');

// ->setApplication()
$t->diag('->setApplication()');
$command = new TestCommand();
$command->setApplication($application);
$t->is($command->getApplication(), $application, '->setApplication() sets the current application');

// ->setDefinition() ->getDefinition()
$t->diag('->setDefinition() ->getDefinition()');
$ret = $command->setDefinition($definition = new InputDefinition());
$t->is($ret, $command, '->setDefinition() implements a fluent interface');
$t->is($command->getDefinition(), $definition, '->setDefinition() sets the current InputDefinition instance');
$command->setDefinition(array(new InputArgument('foo'), new InputOption('bar')));
$t->ok($command->getDefinition()->hasArgument('foo'), '->setDefinition() also takes an array of InputArguments and InputOptions as an argument');
$t->ok($command->getDefinition()->hasOption('bar'), '->setDefinition() also takes an array of InputArguments and InputOptions as an argument');
$command->setDefinition(new InputDefinition());

// ->addArgument()
$t->diag('->addArgument()');
$ret = $command->addArgument('foo');
$t->is($ret, $command, '->addArgument() implements a fluent interface');
$t->ok($command->getDefinition()->hasArgument('foo'), '->addArgument() adds an argument to the command');

// ->addOption()
$t->diag('->addOption()');
$ret = $command->addOption('foo');
$t->is($ret, $command, '->addOption() implements a fluent interface');
$t->ok($command->getDefinition()->hasOption('foo'), '->addOption() adds an option to the command');

// ->getNamespace() ->getName() ->getFullName() ->setName()
$t->diag('->getNamespace() ->getName() ->getFullName()');
$t->is($command->getNamespace(), 'namespace', '->getNamespace() returns the command namespace');
$t->is($command->getName(), 'name', '->getName() returns the command name');
$t->is($command->getFullName(), 'namespace:name', '->getNamespace() returns the full command name');
$command->setName('foo');
$t->is($command->getName(), 'foo', '->setName() sets the command name');

$command->setName(':bar');
$t->is($command->getName(), 'bar', '->setName() sets the command name');
$t->is($command->getNamespace(), '', '->setName() can set the command namespace');

$ret = $command->setName('foobar:bar');
$t->is($ret, $command, '->setName() implements a fluent interface');
$t->is($command->getName(), 'bar', '->setName() sets the command name');
$t->is($command->getNamespace(), 'foobar', '->setName() can set the command namespace');

try
{
  $command->setName('');
  $t->fail('->setName() throws an \InvalidArgumentException if the name is empty');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->setName() throws an \InvalidArgumentException if the name is empty');
}

try
{
  $command->setName('foo:');
  $t->fail('->setName() throws an \InvalidArgumentException if the name is empty');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->setName() throws an \InvalidArgumentException if the name is empty');
}

// ->getDescription() ->setDescription()
$t->diag('->getDescription() ->setDescription()');
$t->is($command->getDescription(), 'description', '->getDescription() returns the description');
$ret = $command->setDescription('description1');
$t->is($ret, $command, '->setDescription() implements a fluent interface');
$t->is($command->getDescription(), 'description1', '->setDescription() sets the description');

// ->getHelp() ->setHelp()
$t->diag('->getHelp() ->setHelp()');
$t->is($command->getHelp(), 'help', '->getHelp() returns the help');
$ret = $command->setHelp('help1');
$t->is($ret, $command, '->setHelp() implements a fluent interface');
$t->is($command->getHelp(), 'help1', '->setHelp() sets the help');

// ->getAliases() ->setAliases()
$t->diag('->getAliases() ->setAliases()');
$t->is($command->getAliases(), array('name'), '->getAliases() returns the aliases');
$ret = $command->setAliases(array('name1'));
$t->is($ret, $command, '->setAliases() implements a fluent interface');
$t->is($command->getAliases(), array('name1'), '->setAliases() sets the aliases');

// ->getSynopsis()
$t->diag('->getSynopsis()');
$t->is($command->getSynopsis(), 'foobar:bar [--foo] [foo]', '->getSynopsis() returns the synopsis');

// ->mergeApplicationDefinition()
$t->diag('->mergeApplicationDefinition()');
$application1 = new Application();
$application1->getDefinition()->addArguments(array(new InputArgument('foo')));
$application1->getDefinition()->addOptions(array(new InputOption('bar')));
$command = new TestCommand();
$command->setApplication($application1);
$command->setDefinition($definition = new InputDefinition(array(new InputArgument('bar'), new InputOption('foo'))));
$command->mergeApplicationDefinition();
$t->ok($command->getDefinition()->hasArgument('foo'), '->mergeApplicationDefinition() merges the application arguments and the command arguments');
$t->ok($command->getDefinition()->hasArgument('bar'), '->mergeApplicationDefinition() merges the application arguments and the command arguments');
$t->ok($command->getDefinition()->hasOption('foo'), '->mergeApplicationDefinition() merges the application options and the command options');
$t->ok($command->getDefinition()->hasOption('bar'), '->mergeApplicationDefinition() merges the application options and the command options');

$command->mergeApplicationDefinition();
$t->is($command->getDefinition()->getArgumentCount(), 3, '->mergeApplicationDefinition() does not try to merge twice the application arguments and options');

$command = new TestCommand();
$command->mergeApplicationDefinition();
$t->pass('->mergeApplicationDefinition() does nothing if application is not set');

// ->run()
$t->diag('->run()');
$command = new TestCommand();
$command->setApplication($application);
$tester = new CommandTester($command);
try
{
  $tester->execute(array('--bar' => true));
  $t->fail('->run() throws a \RuntimeException when the input does not validate the current InputDefinition');
}
catch (\RuntimeException $e)
{
  $t->pass('->run() throws a \RuntimeException when the input does not validate the current InputDefinition');
}

$t->is($tester->execute(array(), array('interactive' => true)), "interact called\nexecute called\n", '->run() calls the interact() method if the input is interactive');
$t->is($tester->execute(array(), array('interactive' => false)), "execute called\n", '->run() does not call the interact() method if the input is not interactive');

$command = new Command('foo');
try
{
  $command->run(new StringInput(''), new NullOutput());
  $t->fail('->run() throws a \LogicException if the execute() method has not been overriden and no code has been provided');
}
catch (\LogicException $e)
{
  $t->pass('->run() throws a \LogicException if the execute() method has not been overriden and no code has been provided');
}

// ->setCode()
$t->diag('->setCode()');
$command = new TestCommand();
$command->setApplication($application);
$ret = $command->setCode(function (InputInterface $input, OutputInterface $output)
{
  $output->writeln('from the code...');
});
$t->is($ret, $command, '->setCode() implements a fluent interface');
$tester = new CommandTester($command);
$tester->execute(array());
$t->is($tester->getDisplay(), "interact called\nfrom the code...\n");

// ->asText()
$t->diag('->asText()');
$t->is($command->asText(), file_get_contents($fixtures.'/command_astext.txt'), '->asText() returns a text representation of the command');

// ->asXml()
$t->diag('->asXml()');
$t->is($command->asXml(), file_get_contents($fixtures.'/command_asxml.txt'), '->asXml() returns an XML representation of the command');
