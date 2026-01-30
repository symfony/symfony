<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Helper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Output\StreamOutput;

#[Group('time-sensitive')]
class ProgressIndicatorTest extends TestCase
{
    public function testDefaultIndicator(): void
    {
        $bar = new ProgressIndicator($output = $this->getOutputStream());
        $bar->start('Starting...');
        usleep(101000);
        $bar->advance();
        usleep(101000);
        $bar->advance();
        usleep(101000);
        $bar->advance();
        usleep(101000);
        $bar->advance();
        usleep(101000);
        $bar->advance();
        usleep(101000);
        $bar->setMessage('Advancing...');
        $bar->advance();
        $bar->finish('Done...');
        $bar->start('Starting Again...');
        usleep(101000);
        $bar->advance();
        $bar->finish('Done Again...');

        rewind($output->getStream());

        $this->assertEquals(
            $this->generateOutput(' - Starting...').
            $this->generateOutput(' \\ Starting...').
            $this->generateOutput(' | Starting...').
            $this->generateOutput(' / Starting...').
            $this->generateOutput(' - Starting...').
            $this->generateOutput(' \\ Starting...').
            $this->generateOutput(' \\ Advancing...').
            $this->generateOutput(' | Advancing...').
            $this->generateOutput(' ✔ Done...').
            \PHP_EOL.
            $this->generateOutput(' - Starting Again...').
            $this->generateOutput(' \\ Starting Again...').
            $this->generateOutput(' ✔ Done Again...').
            \PHP_EOL,
            stream_get_contents($output->getStream())
        );
    }

    public function testNonDecoratedOutput(): void
    {
        $bar = new ProgressIndicator($output = $this->getOutputStream(false));

        $bar->start('Starting...');
        $bar->advance();
        $bar->advance();
        $bar->setMessage('Midway...');
        $bar->advance();
        $bar->advance();
        $bar->finish('Done...');

        rewind($output->getStream());

        $this->assertEquals(
            ' Starting...'.\PHP_EOL.
            ' Midway...'.\PHP_EOL.
            ' Done...'.\PHP_EOL.\PHP_EOL,
            stream_get_contents($output->getStream())
        );
    }

    public function testCustomIndicatorValues(): void
    {
        $bar = new ProgressIndicator($output = $this->getOutputStream(), null, 100, ['a', 'b', 'c']);

        $bar->start('Starting...');
        usleep(101000);
        $bar->advance();
        usleep(101000);
        $bar->advance();
        usleep(101000);
        $bar->advance();

        rewind($output->getStream());

        $this->assertEquals(
            $this->generateOutput(' a Starting...').
            $this->generateOutput(' b Starting...').
            $this->generateOutput(' c Starting...').
            $this->generateOutput(' a Starting...'),
            stream_get_contents($output->getStream())
        );
    }

    public function testCustomFinishedIndicatorValue(): void
    {
        $bar = new ProgressIndicator($output = $this->getOutputStream(), null, 100, ['a', 'b'], '✅');

        $bar->start('Starting...');
        usleep(101000);
        $bar->finish('Done');

        rewind($output->getStream());

        $this->assertSame(
            $this->generateOutput(' a Starting...').
            $this->generateOutput(' ✅ Done').\PHP_EOL,
            stream_get_contents($output->getStream())
        );
    }

    public function testCustomFinishedIndicatorWhenFinishingProcess(): void
    {
        $bar = new ProgressIndicator($output = $this->getOutputStream(), null, 100, ['a', 'b']);

        $bar->start('Starting...');
        $bar->finish('Process failed', '❌');

        rewind($output->getStream());

        $this->assertEquals(
            $this->generateOutput(' a Starting...').
            $this->generateOutput(' ❌ Process failed').\PHP_EOL,
            stream_get_contents($output->getStream())
        );
    }

    public function testCannotSetInvalidIndicatorCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Must have at least 2 indicator value characters.');
        new ProgressIndicator($this->getOutputStream(), null, 100, ['1']);
    }

    public function testCannotStartAlreadyStartedIndicator(): void
    {
        $bar = new ProgressIndicator($this->getOutputStream());
        $bar->start('Starting...');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Progress indicator already started.');

        $bar->start('Starting Again.');
    }

    public function testCannotAdvanceUnstartedIndicator(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Progress indicator has not yet been started.');
        $bar = new ProgressIndicator($this->getOutputStream());
        $bar->advance();
    }

    public function testCannotFinishUnstartedIndicator(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Progress indicator has not yet been started.');
        $bar = new ProgressIndicator($this->getOutputStream());
        $bar->finish('Finished');
    }

    #[DataProvider('provideFormat')]
    public function testFormats($format): void
    {
        $bar = new ProgressIndicator($output = $this->getOutputStream(), $format);
        $bar->start('Starting...');
        $bar->advance();

        rewind($output->getStream());

        $this->assertNotEmpty(stream_get_contents($output->getStream()));
    }

    /**
     * Provides each defined format.
     */
    public static function provideFormat(): array
    {
        return [
            ['normal'],
            ['verbose'],
            ['very_verbose'],
            ['debug'],
        ];
    }

    protected function getOutputStream($decorated = true, $verbosity = StreamOutput::VERBOSITY_NORMAL)
    {
        return new StreamOutput(fopen('php://memory', 'r+', false), $verbosity, $decorated);
    }

    protected function generateOutput($expected)
    {
        $count = substr_count($expected, "\n");

        return "\x0D\x1B[2K".($count ? \sprintf("\033[%dA", $count) : '').$expected;
    }
}
