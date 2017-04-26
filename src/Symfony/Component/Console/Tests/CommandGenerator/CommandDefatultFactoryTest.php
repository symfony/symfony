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

class CommandDefatultFactoryTest extends \PHPUnit_Framework_TestCase
{

    protected static $fixturesPath;

    public static function setUpBeforeClass()
    {
        self::$fixturesPath = __DIR__.'/../Fixtures/';
        require_once self::$fixturesPath.'/Test1Command.php';
    }

    public function testCreateCommandWithDefaultClass()
    {
        $definition = array(
            'name' => 'name',
            'description' => 'description',
            'parameters' => array(
                'param1' => array(
                    'description' => 'description param1',
                ),
            ),
        );

        $commandDefaultFactory = new CommandDefaultFactory();
        $this->assertInstanceOf('Symfony\Component\Console\Command\Command', $commandDefaultFactory->createCommand($definition));
    }

    public function testCreateCommandWithSpecificClass()
    {

        $definition = array(
            'name' => 'name',
            'description' => 'description',
            'parameters' => array(
                'param1' => array(
                    'description' => 'description param1',
                ),
            ),
        );

        $commandDefaultFactory = new CommandDefaultFactory('\Symfony\Component\Console\Tests\Fixtures\Test1Command');
        $this->assertInstanceOf('\Symfony\Component\Console\Tests\Fixtures\Test1Command', $commandDefaultFactory->createCommand($definition));
    }
}
