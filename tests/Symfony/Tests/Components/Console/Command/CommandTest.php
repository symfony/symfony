<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Console\Command;

require_once __DIR__.'/../../../bootstrap.php';

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

class CommandTest extends \PHPUnit_Framework_TestCase
{
  static protected $fixturesPath;

  static public function setUpBeforeClass()
  {
    self::$fixturesPath = __DIR__.'/../../../../../fixtures/Symfony/Components/Console/';
    require_once self::$fixturesPath.'/TestCommand.php';
  }

  public function testConstructor()
  {
    $application = new Application();
    try
    {
      $command = new Command();
      $this->fail('__construct() throws a \LogicException if the name is null');
    }
    catch (\LogicException $e)
    {
    }
    $command = new Command('foo:bar');
    $this->assertEquals($command->getFullName(), 'foo:bar', '__construct() takes the command name as its first argument');
  }

  public function testSetApplication()
  {
    $application = new Application();
    $command = new \TestCommand();
    $command->setApplication($application);
    $this->assertEquals($command->getApplication(), $application, '->setApplication() sets the current application');
  }

  public function testSetGetDefinition()
  {
    $command = new \TestCommand();
    $ret = $command->setDefinition($definition = new InputDefinition());
    $this->assertEquals($ret, $command, '->setDefinition() implements a fluent interface');
    $this->assertEquals($command->getDefinition(), $definition, '->setDefinition() sets the current InputDefinition instance');
    $command->setDefinition(array(new InputArgument('foo'), new InputOption('bar')));
    $this->assertTrue($command->getDefinition()->hasArgument('foo'), '->setDefinition() also takes an array of InputArguments and InputOptions as an argument');
    $this->assertTrue($command->getDefinition()->hasOption('bar'), '->setDefinition() also takes an array of InputArguments and InputOptions as an argument');
    $command->setDefinition(new InputDefinition());
  }

  public function testAddArgument()
  {
    $command = new \TestCommand();
    $ret = $command->addArgument('foo');
    $this->assertEquals($ret, $command, '->addArgument() implements a fluent interface');
    $this->assertTrue($command->getDefinition()->hasArgument('foo'), '->addArgument() adds an argument to the command');
  }

  public function testAddOption()
  {
    $command = new \TestCommand();
    $ret = $command->addOption('foo');
    $this->assertEquals($ret, $command, '->addOption() implements a fluent interface');
    $this->assertTrue($command->getDefinition()->hasOption('foo'), '->addOption() adds an option to the command');
  }

  public function testgetNamespaceGetNameGetFullNameSetName()
  {
    $command = new \TestCommand();
    $this->assertEquals($command->getNamespace(), 'namespace', '->getNamespace() returns the command namespace');
    $this->assertEquals($command->getName(), 'name', '->getName() returns the command name');
    $this->assertEquals($command->getFullName(), 'namespace:name', '->getNamespace() returns the full command name');
    $command->setName('foo');
    $this->assertEquals($command->getName(), 'foo', '->setName() sets the command name');

    $command->setName(':bar');
    $this->assertEquals($command->getName(), 'bar', '->setName() sets the command name');
    $this->assertEquals($command->getNamespace(), '', '->setName() can set the command namespace');

    $ret = $command->setName('foobar:bar');
    $this->assertEquals($ret, $command, '->setName() implements a fluent interface');
    $this->assertEquals($command->getName(), 'bar', '->setName() sets the command name');
    $this->assertEquals($command->getNamespace(), 'foobar', '->setName() can set the command namespace');

    try
    {
      $command->setName('');
      $this->fail('->setName() throws an \InvalidArgumentException if the name is empty');
    }
    catch (\InvalidArgumentException $e)
    {
    }

    try
    {
      $command->setName('foo:');
      $this->fail('->setName() throws an \InvalidArgumentException if the name is empty');
    }
    catch (\InvalidArgumentException $e)
    {
    }
  }

  public function testGetSetDescription()
  {
    $command = new \TestCommand();
    $this->assertEquals($command->getDescription(), 'description', '->getDescription() returns the description');
    $ret = $command->setDescription('description1');
    $this->assertEquals($ret, $command, '->setDescription() implements a fluent interface');
    $this->assertEquals($command->getDescription(), 'description1', '->setDescription() sets the description');
  }

  public function testGetSetHelp()
  {
    $command = new \TestCommand();
    $this->assertEquals($command->getHelp(), 'help', '->getHelp() returns the help');
    $ret = $command->setHelp('help1');
    $this->assertEquals($ret, $command, '->setHelp() implements a fluent interface');
    $this->assertEquals($command->getHelp(), 'help1', '->setHelp() sets the help');
  }

  public function testGetSetAliases()
  {
    $command = new \TestCommand();
    $this->assertEquals($command->getAliases(), array('name'), '->getAliases() returns the aliases');
    $ret = $command->setAliases(array('name1'));
    $this->assertEquals($ret, $command, '->setAliases() implements a fluent interface');
    $this->assertEquals($command->getAliases(), array('name1'), '->setAliases() sets the aliases');
  }

  public function testGetSynopsis()
  {
    $command = new \TestCommand();
    $command->addOption('foo');
    $command->addArgument('foo');
    $this->assertEquals($command->getSynopsis(), 'namespace:name [--foo] [foo]', '->getSynopsis() returns the synopsis');
  }

  public function testMergeApplicationDefinition()
  {
    $application1 = new Application();
    $application1->getDefinition()->addArguments(array(new InputArgument('foo')));
    $application1->getDefinition()->addOptions(array(new InputOption('bar')));
    $command = new \TestCommand();
    $command->setApplication($application1);
    $command->setDefinition($definition = new InputDefinition(array(new InputArgument('bar'), new InputOption('foo'))));
    $command->mergeApplicationDefinition();
    $this->assertTrue($command->getDefinition()->hasArgument('foo'), '->mergeApplicationDefinition() merges the application arguments and the command arguments');
    $this->assertTrue($command->getDefinition()->hasArgument('bar'), '->mergeApplicationDefinition() merges the application arguments and the command arguments');
    $this->assertTrue($command->getDefinition()->hasOption('foo'), '->mergeApplicationDefinition() merges the application options and the command options');
    $this->assertTrue($command->getDefinition()->hasOption('bar'), '->mergeApplicationDefinition() merges the application options and the command options');

    $command->mergeApplicationDefinition();
    $this->assertEquals($command->getDefinition()->getArgumentCount(), 3, '->mergeApplicationDefinition() does not try to merge twice the application arguments and options');

    $command = new \TestCommand();
    $command->mergeApplicationDefinition();
  }

  public function testRun()
  {
    $command = new \TestCommand();
    $application = new Application();
    $command->setApplication($application);
    $tester = new CommandTester($command);
    try
    {
      $tester->execute(array('--bar' => true));
      $this->fail('->run() throws a \RuntimeException when the input does not validate the current InputDefinition');
    }
    catch (\RuntimeException $e)
    {
    }

    $this->assertEquals($tester->execute(array(), array('interactive' => true)), "interact called\nexecute called\n", '->run() calls the interact() method if the input is interactive');
    $this->assertEquals($tester->execute(array(), array('interactive' => false)), "execute called\n", '->run() does not call the interact() method if the input is not interactive');

    $command = new Command('foo');
    try
    {
      $command->run(new StringInput(''), new NullOutput());
      $this->fail('->run() throws a \LogicException if the execute() method has not been overriden and no code has been provided');
    }
    catch (\LogicException $e)
    {
    }
  }

  public function testSetCode()
  {
    $application = new Application();
    $command = new \TestCommand();
    $command->setApplication($application);
    $ret = $command->setCode(function (InputInterface $input, OutputInterface $output)
    {
      $output->writeln('from the code...');
    });
    $this->assertEquals($ret, $command, '->setCode() implements a fluent interface');
    $tester = new CommandTester($command);
    $tester->execute(array());
    $this->assertEquals($tester->getDisplay(), "interact called\nfrom the code...\n");
  }

  public function testAsText()
  {
    $command = new \TestCommand();
    $command->setApplication(new Application());
    $tester = new CommandTester($command);
    $tester->execute(array());
    $this->assertEquals($command->asText(), file_get_contents(self::$fixturesPath.'/command_astext.txt'), '->asText() returns a text representation of the command');
  }

  public function testAsXml()
  {
    $command = new \TestCommand();
    $command->setApplication(new Application());
    $tester = new CommandTester($command);
    $tester->execute(array());
    $this->assertEquals($command->asXml(), file_get_contents(self::$fixturesPath.'/command_asxml.txt'), '->asXml() returns an XML representation of the command');
  }
}
