<?php

use Symfony\Component\Console\CommandGenerator\CommandManager;
use Symfony\Component\Console\CommandGenerator\CommandDiscovery;
use Symfony\Component\Console\Tests\Fixtures\TestCommandResourceBuilder;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CommandGeneratorTest extends \PHPUnit_Framework_TestCase
{

    protected static $fixturesPath;
    protected static $commandManager;
    protected static $commandResourceBuilder;
    protected static $commandDiscovery;

    public static function setUpBeforeClass()
    {
        self::$commandResourceBuilder = new TestCommandResourceBuilder();
        self::$commandDiscovery = new CommandDiscovery(self::$commandResourceBuilder);
        self::$commandManager = new CommandManager(self::$commandDiscovery,'\Symfony\Component\Console\Tests\Fixtures\Test1Command');

        self::$fixturesPath = __DIR__.'/../Fixtures/';
        require_once self::$fixturesPath.'/Test1Command.php';
        require_once self::$fixturesPath.'/TestCommandResourceBuilder.php';
    }

    public function testCommandGeneratorLibrary()
    {

        $application = new Application();
        $application->addCommands($this::$commandManager->generateCommands());

        $fixtures_commands = $this::$commandResourceBuilder->buildDefinitions();

        foreach ($fixtures_commands as $command => $definition) {
            $command = $application->find($definition['name']);
            $commandTester = new CommandTester($command);
            $commandTester->execute(
                array('command' => $command->getName(), 'param1' => 'Value for param1')
            );

            $this->assertRegExp("/Value for param1/", $commandTester->getDisplay());
        }
    }
}
