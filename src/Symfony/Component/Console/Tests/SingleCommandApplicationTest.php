<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\SingleCommandApplication;

class SingleCommandApplicationTest extends \PHPUnit_Framework_TestCase
{
    protected static $fixturesPath;

    public static function setUpBeforeClass()
    {
        self::$fixturesPath = realpath(__DIR__ . '/Fixtures/');
        require_once self::$fixturesPath.'/FooCommand.php';
        require_once self::$fixturesPath . '/FooScaCommand.php';
    }

    public function testConstructor()
    {
        $application = new SingleCommandApplication(new \FooScaCommand(), 'v2.3');
        $this->assertEquals(
            'foosca',
            $application->getName(),
            '__construct() takes the application name as its first argument'
        );
        $this->assertEquals(
            'v2.3',
            $application->getVersion(),
            '__construct() takes the application version as its second argument'
        );
        $this->assertEquals(
            array('help', 'list', 'foosca'),
            array_keys($application->all()),
            '__construct() registered the help and list commands by default'
        );
    }

    /**
     * @dataProvider provideRunData
     */
    public function testRun(InputInterface $input, $expectedOutput, $expectedStatusCode=0)
    {
        // Set up application.
        $application = new SingleCommandApplication(new \FooScaCommand(), '1.234');
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        // Set up output for application to render to.
        $stream = fopen('php://memory', 'w', false);
        $output = new StreamOutput($stream);

        // Run application with given input.
        $statusCode = $application->run($input, $output);

        // Get generated output (and normalize newlines)
        rewind($stream);
        $display = stream_get_contents($stream);
        $display = str_replace(PHP_EOL, "\n", $display);

        $this->assertEquals($expectedStatusCode, $statusCode);
        $this->assertEquals($expectedOutput, $display);
    }

    public function provideRunData()
    {
        $data = array();
        $data[] = array(
            new ArgvInput(array('cli.php')),
            "FooSca (basic)\n",
        );

        $data[] = array(
            new ArgvInput(array('cli.php', 'qwe')),
            "FooSca (basic)\nItem: qwe\n",
        );

        $data[] = array(
            new ArgvInput(array('cli.php', '--bar', 'qwe')),
            "FooSca (barred)\nItem: qwe\n",
        );

        $data[] = array(
            new ArgvInput(array('cli.php', '--bar', 'qwe', 'rty')),
            "FooSca (barred)\nItem: qwe\nItem: rty\n",
        );

        $data[] = array(
            new ArgvInput(array('cli.php', 'list')),
            "FooSca (basic)\nItem: list\n",
        );

        $data[] = array(
            new ArgvInput(array('cli.php', 'help')),
            "FooSca (basic)\nItem: help\n",
        );

        $data[] = array(
            new ArgvInput(array('cli.php', '--help')),
            file_get_contents(__DIR__ . '/Fixtures/' . '/application_run_foosca_help.txt'),
        );

        $data[] = array(
            new ArgvInput(array('cli.php', '-h')),
            file_get_contents(__DIR__ . '/Fixtures/' . '/application_run_foosca_help.txt'),
        );

        $data[] = array(
            new ArgvInput(array('cli.php', '--version')),
            "foosca version 1.234\n"
        );

        $data[] = array(
            new ArgvInput(array('cli.php', '-V')),
            "foosca version 1.234\n"
        );

        return $data;
    }

    public function testAddingMoreCommands()
    {
        $app = new SingleCommandApplication(new \FooScaCommand());
        $this->setExpectedException('LogicException');
        $app->add(new \FooCommand());
    }
}
