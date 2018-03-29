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
                [
                    'getOutput' => $output,
                ]
            )
        );
    }

    public function sampleValueList()
    {
        return [
            [
                'TRUE',
                true,
            ],
            [
                'y',
                true,
            ],
            [
                'yes',
                true,
            ],
            [
                'n',
                false,
            ],
            [
                'false',
                false,
            ],
            [
                uniqid(),
                false,
            ],
        ];
    }

    public function testNotSet()
    {
        putenv(self::$varName.'=');

        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->never())->method('setDecorated');

        $this->fixture->onConsoleCommand(
            $this->createConfiguredMock(
                ConsoleCommandEvent::class,
                [
                    'getOutput' => $output,
                ]
            )
        );
    }

    public function testGetSubscribedEvents()
    {
        $this->assertEquals(
            [
                ConsoleEvents::COMMAND => array('onConsoleCommand', 0),
            ],
            CommandColorListener::getSubscribedEvents()
        );
    }

}
