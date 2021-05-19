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
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Console\Tests\Fixtures\SizableTerminalMock;

class ConsoleSectionOutputTest extends TestCase
{
    private $stream;

    protected function setUp(): void
    {
        $this->stream = fopen('php://memory', 'r+', false);
    }

    protected function tearDown(): void
    {
        $this->stream = null;
    }

    public function testClearAll()
    {
        $sections = [];
        $output = new ConsoleSectionOutput($this->stream, $sections, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());

        $output->writeln('Foo'.\PHP_EOL.'Bar');
        $output->clear();

        rewind($output->getStream());
        $this->assertEquals('Foo'.\PHP_EOL.'Bar'.\PHP_EOL.sprintf("\x1b[%dA", 2)."\x1b[0J", stream_get_contents($output->getStream()));
    }

    public function testClearNumberOfLines()
    {
        $sections = [];
        $output = new ConsoleSectionOutput($this->stream, $sections, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());

        $output->writeln("Foo\nBar\nBaz\nFooBar");
        $output->clear(2);

        rewind($output->getStream());
        $this->assertEquals("Foo\nBar\nBaz\nFooBar".\PHP_EOL.sprintf("\x1b[%dA", 2)."\x1b[0J", stream_get_contents($output->getStream()));
    }

    public function testClearNumberOfLinesWithMultipleSections()
    {
        $output = new StreamOutput($this->stream);
        $sections = [];
        $output1 = new ConsoleSectionOutput($output->getStream(), $sections, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());
        $output2 = new ConsoleSectionOutput($output->getStream(), $sections, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());

        $output2->writeln('Foo');
        $output2->writeln('Bar');
        $output2->clear(1);
        $output1->writeln('Baz');

        rewind($output->getStream());

        $this->assertEquals('Foo'.\PHP_EOL.'Bar'.\PHP_EOL."\x1b[1A\x1b[0J\e[1A\e[0J".'Baz'.\PHP_EOL.'Foo'.\PHP_EOL, stream_get_contents($output->getStream()));
    }

    public function testClearPreservingEmptyLines()
    {
        $output = new StreamOutput($this->stream);
        $sections = [];
        $output1 = new ConsoleSectionOutput($output->getStream(), $sections, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());
        $output2 = new ConsoleSectionOutput($output->getStream(), $sections, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());

        $output2->writeln(\PHP_EOL.'foo');
        $output2->clear(1);
        $output1->writeln('bar');

        rewind($output->getStream());

        $this->assertEquals(\PHP_EOL.'foo'.\PHP_EOL."\x1b[1A\x1b[0J\x1b[1A\x1b[0J".'bar'.\PHP_EOL.\PHP_EOL, stream_get_contents($output->getStream()));
    }

    public function testOverwrite()
    {
        $sections = [];
        $output = new ConsoleSectionOutput($this->stream, $sections, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());

        $output->writeln('Foo');
        $output->overwrite('Bar');

        rewind($output->getStream());
        $this->assertEquals('Foo'.\PHP_EOL."\x1b[1A\x1b[0JBar".\PHP_EOL, stream_get_contents($output->getStream()));
    }

    public function testOverwriteMultipleLines()
    {
        $sections = [];
        $output = new ConsoleSectionOutput($this->stream, $sections, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());

        $output->writeln('Foo'.\PHP_EOL.'Bar'.\PHP_EOL.'Baz');
        $output->overwrite('Bar');

        rewind($output->getStream());
        $this->assertEquals('Foo'.\PHP_EOL.'Bar'.\PHP_EOL.'Baz'.\PHP_EOL.sprintf("\x1b[%dA", 3)."\x1b[0J".'Bar'.\PHP_EOL, stream_get_contents($output->getStream()));
    }

    public function testAddingMultipleSections()
    {
        $sections = [];
        new ConsoleSectionOutput($this->stream, $sections, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());
        new ConsoleSectionOutput($this->stream, $sections, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());

        $this->assertCount(2, $sections);
    }

    public function testMultipleSectionsOutput()
    {
        $output = new StreamOutput($this->stream);
        $sections = [];
        $output1 = new ConsoleSectionOutput($output->getStream(), $sections, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());
        $output2 = new ConsoleSectionOutput($output->getStream(), $sections, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());

        $output1->writeln('Foo');
        $output2->writeln('Bar');

        $output1->overwrite('Baz');
        $output2->overwrite('Foobar');

        rewind($output->getStream());
        $this->assertEquals('Foo'.\PHP_EOL.'Bar'.\PHP_EOL."\x1b[2A\x1b[0JBaz".\PHP_EOL.'Bar'.\PHP_EOL."\x1b[1A\x1b[0JFoobar".\PHP_EOL, stream_get_contents($output->getStream()));
    }

    public function testClearSectionContainingQuestion()
    {
        $inputStream = fopen('php://memory', 'r+', false);
        fwrite($inputStream, "Batman & Robin\n");
        rewind($inputStream);

        $input = $this->createMock(StreamableInputInterface::class);
        $input->expects($this->once())->method('isInteractive')->willReturn(true);
        $input->expects($this->once())->method('getStream')->willReturn($inputStream);

        $sections = [];
        $output = new ConsoleSectionOutput($this->stream, $sections, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter());

        (new QuestionHelper())->ask($input, $output, new Question('What\'s your favorite super hero?'));
        $output->clear();

        rewind($output->getStream());
        $this->assertSame('What\'s your favorite super hero?'.\PHP_EOL."\x1b[2A\x1b[0J", stream_get_contents($output->getStream()));
    }

    public function testClearAboveTerminal()
    {
        [$section1, $section2] = $this->prepareSectionsInSizedTerminal(2, 3, 10);

        $section1->writeln('foo');

        // push section1 out of terminal
        $section2->writeln(implode(\PHP_EOL, ['1', '2', '3', '4', '5']));

        // should not trigger re-write as section is not visible
        $section1->clear();

        rewind($this->stream);
        $this->assertEquals(implode(\PHP_EOL, ['foo', '1', '2', '3', '4', '5', '']), stream_get_contents($this->stream));
    }

    public function testClearOnTerminalEdge()
    {
        [$section1, $section2] = $this->prepareSectionsInSizedTerminal(2, 5, 10);

        $section1->writeln(implode(\PHP_EOL, ['1', '2', '3']));

        // push section1 on edge of terminal
        $section2->writeln(implode(\PHP_EOL, ['4', '5', '6']));

        $section1->clear();

        rewind($this->stream);
        $this->assertEquals(implode(\PHP_EOL, ['1', '2', '3', '4', '5', '6', "\x1b[5A\x1b[0J4", '5', '6', '']), stream_get_contents($this->stream));
    }

    public function testWriteAboveTerminal()
    {
        [$section1, $section2] = $this->prepareSectionsInSizedTerminal(2, 3, 10);

        $section1->writeln('foo');

        // push section1 out of terminal
        $section2->writeln(implode(\PHP_EOL, ['1', '2', '3', '4', '5']));

        // should not be written as section is not visible on terminal
        $section1->writeln('bar');

        rewind($this->stream);
        $this->assertEquals(implode(\PHP_EOL, ['foo', '1', '2', '3', '4', '5', '']), stream_get_contents($this->stream));
    }

    public function testOverwriteAboveTerminal()
    {
        [$section] = $this->prepareSectionsInSizedTerminal(1, 3, 10);

        $section->writeln(implode(\PHP_EOL, ['foo', '1', '2', '3', '4', '5']));

        // should have no effect as overwritten portion is not visible
        $section->overwrite(implode(\PHP_EOL, ['bar', '1', '2', '3', '4', '5']));

        rewind($this->stream);
        $this->assertEquals(implode(\PHP_EOL, ['foo', '1', '2', '3', '4', '5', '']), stream_get_contents($this->stream));
    }

    public function testOverwriteOnTerminalEdge()
    {
        [$section] = $this->prepareSectionsInSizedTerminal(1, 3, 10);

        $section->writeln(implode(\PHP_EOL, ['1', '2', '3', '4', '5']));

        // should overwrite only those lines that are on the terminal
        $section->overwrite(implode(\PHP_EOL, ['6', '7', '8', '9', '10']));

        rewind($this->stream);
        $this->assertEquals(implode(\PHP_EOL, ['1', '2', '3', '4', '5', "\x1b[3A\x1b[0J8", '9', '10', '']), stream_get_contents($this->stream));
    }

    public function testOverwriteAboveTerminalInDifferentSection()
    {
        [$section1, $section2] = $this->prepareSectionsInSizedTerminal(2, 3, 10);

        $section1->writeln(implode(\PHP_EOL, ['foo']));

        // push section1 on edge of terminal
        $section2->writeln(implode(\PHP_EOL, ['1', '2', '3', '4']));

        // should have no effect as section1 is not visible
        $section1->overwrite('bar');

        rewind($this->stream);
        $this->assertEquals(implode(\PHP_EOL, ['foo', '1', '2', '3', '4', '']), stream_get_contents($this->stream));
    }

    public function testOverwriteOnTerminalEdgeInDifferentSection()
    {
        [$section1, $section2] = $this->prepareSectionsInSizedTerminal(2, 5, 10);

        $section1->writeln(implode(\PHP_EOL, ['1', '2', '3']));

        // push section1 on edge of terminal
        $section2->writeln(implode(\PHP_EOL, ['4', '5', '6']));

        // should re-write only visible portion of section1
        $section1->overwrite(implode(\PHP_EOL, ['foo', 'bar', 'baz']));

        rewind($this->stream);
        $this->assertEquals(implode(\PHP_EOL, ['1', '2', '3', '4', '5', '6', "\x1b[5A\x1b[0Jbar", 'baz', '4', '5', '6', '']), stream_get_contents($this->stream));
    }

    public function testOverwriteOnTerminalEdgeWithOverhang()
    {
        [$section] = $this->prepareSectionsInSizedTerminal(1, 3, 10);

        $section->writeln(implode(\PHP_EOL, ['1', '2', '3', '4', '5']));

        // should overwrite only those lines that are on the terminal
        $section->overwrite(implode(\PHP_EOL, ['foo', 'bar', 'bazbazbazbaz', '5']));

        rewind($this->stream);
        $this->assertEquals(implode(\PHP_EOL, ['1', '2', '3', '4', '5', "\x1b[3A\x1b[0Jbazbazbazbaz", '5', '']), stream_get_contents($this->stream));
    }

    public function testOverwriteOnTerminalEdgeWithOverhangInDifferentSection()
    {
        [$section1, $section2] = $this->prepareSectionsInSizedTerminal(2, 5, 10);

        $section1->writeln(implode(\PHP_EOL, ['1', '2', '3']));

        // push section1 on edge of terminal
        $section2->writeln(implode(\PHP_EOL, ['4', '5', '6']));

        // should re-write only last text overhanging to two lines
        $section1->overwrite(implode(\PHP_EOL, ['foo', 'bar', 'bazbazbazbaz']));

        rewind($this->stream);
        $this->assertEquals(implode(\PHP_EOL, ['1', '2', '3', '4', '5', '6', "\x1b[5A\x1b[0Jbazbazbazbaz", '4', '5', '6', '']), stream_get_contents($this->stream));
    }

    public function testClearIsCompletedAfterTerminalResize()
    {
        putenv('LINES=3');
        putenv('COLUMNS=10');

        $terminal = new Terminal();

        $sections = [];
        $section = new ConsoleSectionOutput($this->stream, $sections, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter(), $terminal);

        // content does not fit on terminal
        $section->writeln(implode(\PHP_EOL, ['1', '2', '3', '4', '5', '6']));

        // only visible portion can be cleared
        $section->clear();

        // resize terminal
        putenv('LINES=10');

        // any interaction should now finalize the incomplete clear
        $section->writeln('foo');

        rewind($this->stream);
        $this->assertEquals(implode(\PHP_EOL, ['1', '2', '3', '4', '5', '6', "\x1b[3A\x1b[0J\x1b[3A\x1b[0Jfoo", '']), stream_get_contents($this->stream));
    }

    public function testClearsAreCompletedAfterTerminalResize()
    {
        putenv('LINES=3');
        putenv('COLUMNS=10');

        $terminal = new Terminal();

        $sections = [];
        $section = new ConsoleSectionOutput($this->stream, $sections, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter(), $terminal);

        // content does not fit on terminal
        $section->writeln(implode(\PHP_EOL, ['1', '2', '3', '4', '5', '6']));

        // only visible portion can be cleared
        $section->clear(4);
        $section->clear(2);

        // resize terminal
        putenv('LINES=10');

        // any interaction should now finalize the incomplete clear
        $section->writeln('foo');

        rewind($this->stream);
        $this->assertEquals(implode(\PHP_EOL, ['1', '2', '3', '4', '5', '6', "\x1b[3A\x1b[0J\x1b[3A\x1b[0Jfoo", '']), stream_get_contents($this->stream));
    }

    /**
     * @return ConsoleSectionOutput[]
     */
    private function prepareSectionsInSizedTerminal(int $numSections, int $terminalHeight, int $terminalWidth): array
    {
        // cannot use phpunit mocks as static functions have to be callable for terminal resize listener (de)registration
        $terminal = new SizableTerminalMock($terminalWidth, $terminalHeight);

        $sections = [];
        for($i = 0; $i < $numSections; $i++) {
            new ConsoleSectionOutput($this->stream, $sections, OutputInterface::VERBOSITY_NORMAL, true, new OutputFormatter(), $terminal);
        }

        return array_reverse($sections);
    }
}
