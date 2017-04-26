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

use Symfony\Component\Console\CommandGenerator\CommandDiscovery;
use Symfony\Component\Console\CommandGenerator\CommandManager;
use Symfony\Component\Console\Tests\Fixtures\TestCommandResourceBuilder;

class CommandManagerTest extends \PHPUnit_Framework_TestCase
{

    protected static $fixturesPath;
    protected static $commandManager;
    protected static $commandResourceBuilder;
    protected static $commandDiscovery;

    public static function setUpBeforeClass()
    {
        self::$commandResourceBuilder = new TestCommandResourceBuilder();
        self::$commandDiscovery = new CommandDiscovery(self::$commandResourceBuilder);
        self::$commandManager = new CommandManager(self::$commandDiscovery, '\Symfony\Component\Console\Tests\Fixtures\Test1Command');
        self::$fixturesPath = __DIR__.'/../Fixtures/';
        require_once self::$fixturesPath.'/Test1Command.php';
        require_once self::$fixturesPath.'/TestCommandResourceBuilder.php';
    }

    public function testGenerateCommands()
    {
        $commands = $this::$commandManager->generateCommands();
        $this->assertCount(3, $commands);
        $this->assertContainsOnly('\Symfony\Component\Console\Tests\Fixtures\Test1Command', $commands);
    }
}
