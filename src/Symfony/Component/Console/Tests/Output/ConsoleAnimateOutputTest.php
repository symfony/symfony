<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Output;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\ConsoleAnimateOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

class ConsoleAnimateOutputTest extends TestCase
{
    private $stream;

    protected function setUp(): void
    {
        $this->stream = fopen('php://memory', 'r+b', false);
    }

    protected function tearDown(): void
    {
        $this->stream = null;
    }

    public function testWait(): void
    {
        $output = new ConsoleAnimateOutput($this->stream, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter(), ConsoleAnimateOutput::NO_WRITE_ANIMATION);

        $expectedTime = (int) microtime(true) + 1;
        $output->wait();
        $finalTime = (int) microtime(true);
        $this->assertEqualsWithDelta($expectedTime, $finalTime, 0.0001);
    }

    public function testWriteWithoutSlowDown(): void
    {
        $output = new ConsoleAnimateOutput($this->stream, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter(), ConsoleAnimateOutput::NO_WRITE_ANIMATION);

        $time1 = (int) microtime(true);
        $output->write('Lorem ipsum dolor sit amet');
        $time2 = (int) microtime(true);
        $this->assertEqualsWithDelta($time1, $time2, 0.001);
    }

    public function testWriteWithNormalSlowDown(): void
    {
        $output = new ConsoleAnimateOutput($this->stream, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());
        // WRITE_NORMAL write 1 char each 0.05 sec

        $expectedTime = microtime(true) + 0.5; // ten letters
        $output->write('Loremipsum');
        $finalTime = microtime(true);

        $this->assertEqualsWithDelta($expectedTime, $finalTime, 0.01);
    }

    public function testClearCurrentLine(): void
    {
        $output = new ConsoleAnimateOutput($this->stream, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());

        $output->writeln('Foo'.PHP_EOL.'Bar');
        $output->clear();
        // Current line will be a newLine
        rewind($output->getStream());
        $this->assertEquals('Foo'.PHP_EOL.'Bar'.PHP_EOL.PHP_EOL."\x1b[0A"."\x1b[2K", stream_get_contents($output->getStream()));
    }

    public function testOverwrite(): void
    {
        $output = new ConsoleAnimateOutput($this->stream, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());

        $output->write('Foo');
        $output->overwrite('Bar');
        rewind($output->getStream());
        $this->assertEquals('Foo'.PHP_EOL."\x1b[0A\x1b[2KBar", stream_get_contents($output->getStream()));
    }

    public function testOverwriteMultipleLine(): void
    {
        $output = new ConsoleAnimateOutput($this->stream, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());

        $output->write('Foo' . PHP_EOL . 'Bar');
        $output->overwrite('Bar', 2);
        rewind($output->getStream());
        $this->assertEquals('Foo'.PHP_EOL.'Bar'."\x1b[2A\x1b[0JBar", stream_get_contents($output->getStream()));
    }

    public function testOverwriteLn(): void
    {
        $output = new ConsoleAnimateOutput($this->stream, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());

        $output->write('Foo');
        $output->overwriteln('Bar');
        rewind($output->getStream());
        $this->assertEquals('Foo'.PHP_EOL."\x1b[0A\x1b[2KBar".PHP_EOL, stream_get_contents($output->getStream()));
    }

    public function testChangeSlowDownToZero(): void
    {
        $output = new ConsoleAnimateOutput($this->stream, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());

        $output->setSlowDown(ConsoleAnimateOutput::NO_WRITE_ANIMATION);
        $time1 = microtime(true);
        $output->write('Loremipsum');
        $time2 = microtime(true);

        $this->assertEqualsWithDelta($time1, $time2, 0.001);
    }

    public function testChangeSlowDownToVeryVerySlow(): void
    {
        $output = new ConsoleAnimateOutput($this->stream, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());

        $output->setSlowDown(ConsoleAnimateOutput::WRITE_VERY_VERY_SLOW);
        // WRITE_VERY_VERY_SLOW write 1 char each sec
        $expectedTime = microtime(true) + 3;
        $output->write('Foo');
        $finalTime = microtime(true);

        $this->assertEqualsWithDelta($expectedTime, $finalTime, 0.01);
    }

    public function testGetSlowDown(): void
    {
        $output = new ConsoleAnimateOutput($this->stream, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());

        $this->assertEquals(ConsoleAnimateOutput::WRITE_NORMAL, $output->getSlowDown());

        $output->setSlowDown(42);

        $this->assertEquals(42, $output->getSlowDown());
    }

    public function testGetUsleepDuration(): void
    {
        $output = new ConsoleAnimateOutput($this->stream, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());

        $this->assertEquals(50000, $output->getUsleepDuration());

        $output->setSlowDown(42);

        $this->assertEquals(5000 * 42, $output->getUsleepDuration());
    }

    public function testChangeEffect(): void
    {
        $output = new ConsoleAnimateOutput($this->stream, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());
        $noOutputEffect = static function ($message, $newline) {};

        $output->setCustomEffect($noOutputEffect);

        $time1 = microtime(true);
        $output->write('Symfony');
        $time2 = microtime(true);
        rewind($output->getStream());

        $this->assertEqualsWithDelta($time1, $time2, 0.001);
        $this->assertEmpty(stream_get_contents($output->getStream()));
    }

    public function testChangeEffectThrowErrorWhenClosureIsMalformed(): void
    {
        $this->expectException(\Error::class);
        $output = new ConsoleAnimateOutput($this->stream, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());
        $badEffect = static function (string $message, bool $newLine, $unexpectedArs) { echo $message; };

        $output->setCustomEffect($badEffect);
        $output->write('Foo');
    }

    public function testDirectWrite(): void
    {
        $output = new ConsoleAnimateOutput($this->stream, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());

        $time1 = microtime(true);
        $output->directWrite('Symfony');
        $time2 = microtime(true);

        $this->assertEqualsWithDelta($time1, $time2, 0.0001);
    }

    public function testHideCursor(): void
    {
        $output = new ConsoleAnimateOutput($this->stream, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());
        $output->hideCursor();

        rewind($output->getStream());
        $this->assertEquals("\033[?25l", stream_get_contents($output->getStream()));
    }

    public function testShowCursor(): void
    {
        $output = new ConsoleAnimateOutput($this->stream, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());
        $output->showCursor();

        rewind($output->getStream());
        $this->assertEquals("\033[?25h", stream_get_contents($output->getStream()));
    }

    public function testResetEffect(): void
    {
        $output = new ConsoleAnimateOutput($this->stream, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());

        $output->setCustomEffect(function() {});
        $output->resetEffect();

        $expectedTime = microtime(true) + 0.5; // ten letters
        $output->write('Loremipsum');
        $finalTime = microtime(true);

        $this->assertEqualsWithDelta($expectedTime, $finalTime, 0.01);
    }

    public function testCleanScreen(): void
    {
        $output = new ConsoleAnimateOutput($this->stream, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());
        $lineToClear = (new Terminal())->getHeight();
        $output->clearScreen();

        rewind($output->getStream());
        $this->assertEquals(sprintf("\033[%dA\033[0J", $lineToClear), stream_get_contents($output->getStream()));
    }
}
