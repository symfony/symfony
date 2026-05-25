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
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\StreamOutput;

#[Group('time-sensitive')]
class ProgressIndicatorTest extends TestCase
{
    public function testDefaultIndicator()
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

    public function testNonDecoratedOutput()
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

    public function testCustomIndicatorValues()
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

    public function testCustomFinishedIndicatorValue()
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

    public function testCustomFinishedIndicatorWhenFinishingProcess()
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

    public function testCannotSetInvalidIndicatorCharacters()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Must have at least 2 indicator value characters.');
        new ProgressIndicator($this->getOutputStream(), null, 100, ['1']);
    }

    public function testCannotStartAlreadyStartedIndicator()
    {
        $bar = new ProgressIndicator($this->getOutputStream());
        $bar->start('Starting...');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Progress indicator already started.');

        $bar->start('Starting Again.');
    }

    public function testCannotAdvanceUnstartedIndicator()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Progress indicator has not yet been started.');
        $bar = new ProgressIndicator($this->getOutputStream());
        $bar->advance();
    }

    public function testCannotFinishUnstartedIndicator()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Progress indicator has not yet been started.');
        $bar = new ProgressIndicator($this->getOutputStream());
        $bar->finish('Finished');
    }

    #[DataProvider('provideFormat')]
    public function testFormats($format)
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

    public function testWithConsoleSectionOutput()
    {
        $sections = [];
        $stream = fopen('php://memory', 'r+', false);
        $output = new ConsoleSectionOutput($stream, $sections, StreamOutput::VERBOSITY_NORMAL, true, new OutputFormatter());

        $bar = new ProgressIndicator($output, null, 100, ['-', '\\', '|', '/']);
        $bar->start('Starting...');
        usleep(101000);
        $bar->advance();
        $bar->finish('Done...');

        rewind($stream);
        $content = stream_get_contents($stream);

        // Must not use raw ANSI line-clear sequences — those corrupt ConsoleSectionOutput's internal line tracking
        $this->assertStringNotContainsString("\x0D\x1B[2K", $content);

        // finish() must not add an extra trailing newline — ConsoleSectionOutput::overwrite() already ends with writeln()
        $this->assertStringEndsWith(' ✔ Done...'.\PHP_EOL, $content);
    }

    public function testMultipleSectionsWithProgressIndicators()
    {
        $sections = [];
        $stream = fopen('php://memory', 'r+', false);
        $formatter = new OutputFormatter();
        $section1 = new ConsoleSectionOutput($stream, $sections, StreamOutput::VERBOSITY_NORMAL, true, $formatter);
        $section2 = new ConsoleSectionOutput($stream, $sections, StreamOutput::VERBOSITY_NORMAL, true, $formatter);

        $bar1 = new ProgressIndicator($section1, null, 100, ['-', '\\', '|', '/']);
        $bar2 = new ProgressIndicator($section2, null, 100, ['-', '\\', '|', '/']);

        $bar1->start('Project 1...');
        $bar2->start('Project 2...');
        usleep(101000);
        $bar1->advance();
        $bar2->advance();
        $bar1->finish('Project 1 Done.');
        $bar2->finish('Project 2 Done.');

        rewind($stream);
        $content = stream_get_contents($stream);

        // Must not use raw ANSI line-clear sequences
        $this->assertStringNotContainsString("\x0D\x1B[2K", $content);

        // Both finished messages must appear in the output
        $this->assertStringContainsString('Project 1 Done.', $content);
        $this->assertStringContainsString('Project 2 Done.', $content);
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
