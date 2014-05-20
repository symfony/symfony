<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\CommandGenerator;

use Symfony\Component\Console\CommandGenerator\CommandDefaultFactory;
use Symfony\Component\Console\CommandGenerator\CommandDiscovery;
use Symfony\Component\Console\Tests\Fixtures\TestCommandResourceBuilder;

class CommandDiscoveryTest extends \PHPUnit_Framework_TestCase
{

    protected static $fixturesPath;
    protected static $commandFactory;
    protected static $commandResourceBuilder;
    protected static $commandDiscovery;

    public static function setUpBeforeClass()
    {
        self::$commandFactory = new CommandDefaultFactory('\Symfony\Component\Console\Tests\Fixtures\Test1Command');
        self::$commandResourceBuilder = new TestCommandResourceBuilder();
        self::$commandDiscovery = new CommandDiscovery(self::$commandResourceBuilder, self::$commandFactory);
        self::$fixturesPath = __DIR__.'/../Fixtures/';
        require_once self::$fixturesPath.'/Test1Command.php';
        require_once self::$fixturesPath.'/TestCommandResourceBuilder.php';
    }

    public function testBuildDefinitions()
    {
        $definitions = $this::$commandDiscovery->buildDefinitions();
        $this->assertArrayHasKey('command1', $definitions);
        $this->assertArrayHasKey('name', $definitions['command1']);
        $this->assertArrayHasKey('command2', $definitions);
        $this->assertArrayHasKey('name', $definitions['command1']);
        $this->assertArrayHasKey('command3', $definitions);
        $this->assertArrayHasKey('name', $definitions['command3']);
    }

    public function testGenerateCommand()
    {
        $definitions = $this::$commandDiscovery->buildDefinitions();
        $command = $this::$commandDiscovery->generateCommand($definitions['command1']);
        $this->assertInstanceOf('\Symfony\Component\Console\Tests\Fixtures\Test1Command', $command);
    }

    public function testGenerateCommands()
    {
        $definitions = $this::$commandDiscovery->buildDefinitions();
        $commands = $this::$commandDiscovery->generateCommands($definitions);
        $this->assertCount(3, $commands);
        $this->assertContainsOnly('\Symfony\Component\Console\Tests\Fixtures\Test1Command', $commands);
    }
}
