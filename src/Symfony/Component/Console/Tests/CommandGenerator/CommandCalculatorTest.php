<?php

use Symfony\Component\Console\CommandGenerator\CommandManager;
use Symfony\Component\Console\CommandGenerator\CommandDiscovery;
use Symfony\Component\Console\Tests\Fixtures\CalculatorResourceBuilder;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CommandCalculatorTest extends \PHPUnit_Framework_TestCase
{

    protected static $fixturesPath;
    protected static $commandManager;
    protected static $commandResourceBuilder;
    protected static $commandDiscovery;

    public static function setUpBeforeClass()
    {
        self::$commandResourceBuilder = new CalculatorResourceBuilder();
        self::$commandDiscovery = new CommandDiscovery(self::$commandResourceBuilder);
        self::$commandManager = new CommandManager(self::$commandDiscovery,'\Symfony\Component\Console\Tests\Fixtures\CalculatorCommand');

        self::$fixturesPath = __DIR__.'/../Fixtures/';
        require_once self::$fixturesPath.'/CalculatorCommand.php';
    }

    public function testCommandGeneratorLibrary()
    {

        $application = new Application();
        $application->addCommands($this::$commandManager->generateCommands());

        $fixtures_commands = array(
            'calculator:max' => array(
                'params' => array(
                    'value1' => 10,
                    'value2' => 6,
                ),
                'expected' => 10,
            ),
            'calculator:min' => array(
                'params' => array(
                    'value1' => 10,
                    'value2' => 6,
                ),
                'expected' => 6,
            ),
            'calculator:abs' => array(
                'params' => array(
                    'value1' => -110,
                ),
                'expected' => 110,
            ),
            'calculator:sin' => array(
                'params' => array(
                    'value1' => M_PI_2,
                ),
                'expected' => 1,
            ),
        );

        foreach($fixtures_commands as $command => $definition) {
            $command = $application->find($command);
            $commandTester = new CommandTester($command);

            $definition['params']['command'] = $command->getName();
            $commandTester->execute($definition['params']);

            $this->assertTrue($definition['expected'] == $commandTester->getDisplay());
        }
    }
}