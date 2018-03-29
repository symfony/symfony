<?php

namespace Symfony\Component\Console\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\EventListener\CommandColorListener;
use Symfony\Component\Console\Output\OutputInterface;

class CommandColorListenerTest extends TestCase
{
    /**
     * @var CommandColorListener
     */
    protected $fixture = null;

    /**
     * @var string
     */
    protected static $varName = null;

    public static function setUpBeforeClass()
    {
        do {
            self::$varName = uniqid('CommandColorListener');
        } while (!empty(getenv(self::$varName)));
    }

    protected function setUp()
    {
        parent::setUp();

        $this->fixture = new CommandColorListener(self::$varName);
    }

    protected function tearDown()
    {
        if (!empty(self::$varName)) {
            putenv(self::$varName.'=');
        }
    }

    /**
     * @dataProvider sampleValueList
     */
    public function testSetDecorated($set, $expected)
    {
        putenv(self::$varName.'='.$set);

        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())->method('setDecorated')->with($expected);

        $this->fixture->onConsoleCommand(
            $this->createConfiguredMock(
                ConsoleCommandEvent::class,
                array(
                    'getOutput' => $output,
                )
            )
        );
    }

    public function sampleValueList()
    {
        return array(
            array(
                'TRUE',
                true,
            ),
            array(
                'y',
                true,
            ),
            array(
                'yes',
                true,
            ),
            array(
                'n',
                false,
            ),
            array(
                'false',
                false,
            ),
            array(
                uniqid(),
                false,
            ),
        );
    }

    public function testNotSet()
    {
        putenv(self::$varName.'=');

        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->never())->method('setDecorated');

        $this->fixture->onConsoleCommand(
            $this->createConfiguredMock(
                ConsoleCommandEvent::class,
                array(
                    'getOutput' => $output,
                )
            )
        );
    }

    public function testGetSubscribedEvents()
    {
        $this->assertEquals(
            array(
                ConsoleEvents::COMMAND => array('onConsoleCommand', 0),
            ),
            CommandColorListener::getSubscribedEvents()
        );
    }
}
