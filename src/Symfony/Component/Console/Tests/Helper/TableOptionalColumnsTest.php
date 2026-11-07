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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

class TableOptionalColumnsTest extends TestCase
{
    #[DataProvider('provideOptionalColumns')]
    public function testOptionalColumns(string $style, int $width, array $optional, array $expectedRows)
    {
        $output = new BufferedOutput();
        (new Table($output))
            ->setStyle($style)
            ->setRows([['aaa', 'bbbb', 'cc']])
            ->setOptionalColumns($optional)
            ->setMaxWidth($width)
            ->render();

        $expected = new BufferedOutput();
        (new Table($expected))->setStyle($style)->setRows($expectedRows)->render();

        $this->assertSame($expected->fetch(), $output->fetch());
    }

    public static function provideOptionalColumns(): iterable
    {
        yield 'exact fit includes borders and padding' => ['default', 19, [1, 2], [['aaa', 'bbbb', 'cc']]];
        yield 'drop rightmost first regardless of option order' => ['default', 18, [2, 1], [['aaa', 'bbbb']]];
        yield 'drop middle column' => ['default', 12, [1], [['aaa', 'cc']]];
        yield 'mandatory columns may exceed the limit' => ['default', 1, [1], [['aaa', 'cc']]];
        yield 'all columns can be optional' => ['default', 1, [0, 1, 2], []];
        yield 'no optional columns' => ['default', 1, [], [['aaa', 'bbbb', 'cc']]];
        yield 'compact exact fit' => ['compact', 12, [1, 2], [['aaa', 'bbbb', 'cc']]];
        yield 'compact drop' => ['compact', 11, [1, 2], [['aaa', 'bbbb']]];
        yield 'unicode borders' => ['box', 18, [1, 2], [['aaa', 'bbbb']]];
        yield 'markdown' => ['markdown', 18, [1, 2], [['aaa', 'bbbb']]];
        yield 'unknown column has no effect' => ['default', 1, [10], [['aaa', 'bbbb', 'cc']]];
    }

    public function testSpanningCellsAndSeparators()
    {
        $cell = new TableCell('group', ['colspan' => 3, 'style' => new TableCellStyle(['align' => 'right'])]);
        $output = new BufferedOutput();
        $table = (new Table($output))
            ->setHeaders([[$cell], ['A', 'B', 'C']])
            ->setRows([['a', 'hidden', 'c'], new TableSeparator(), ['d', 'hidden', 'f']])
            ->setOptionalColumns([0, 1])
            ->setMaxWidth(5);
        $table->render();

        $expected = new BufferedOutput();
        (new Table($expected))
            ->setHeaders([[new TableCell('group', ['style' => $cell->getStyle()])], ['C']])
            ->setRows([['c'], new TableSeparator(), ['f']])
            ->render();

        $this->assertSame($expected->fetch(), $output->fetch());
        $this->assertSame(3, $cell->getColspan());
    }

    public function testRowspans()
    {
        $output = new BufferedOutput();
        (new Table($output))
            ->setRows([[new TableCell('a', ['rowspan' => 2]), 'hidden', 'b'], ['hidden', 'c']])
            ->setOptionalColumns([1])
            ->setMaxWidth(9)
            ->render();

        $expected = new BufferedOutput();
        (new Table($expected))->setRows([[new TableCell('a', ['rowspan' => 2]), 'b'], ['c']])->render();

        $this->assertSame($expected->fetch(), $output->fetch());
    }

    #[DataProvider('provideStyledRowspanValues')]
    public function testStyledMultilineRowspan(string $value, int $colspan)
    {
        $style = new TableCellStyle(['fg' => 'red', 'bg' => 'blue']);
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
        (new Table($output))
            ->setRows([[new TableCell($value, ['rowspan' => 2, 'colspan' => $colspan, 'style' => $style]), ...($colspan > 1 ? ['b'] : ['hidden', 'b'])], $colspan > 1 ? ['c'] : ['hidden', 'c']])
            ->setOptionalColumns([1])
            ->setMaxWidth(14)
            ->render();

        $expected = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
        (new Table($expected))->setRows([[new TableCell($value, ['rowspan' => 2, 'style' => $style]), 'b'], ['c']])->render();
        $this->assertSame($expected->fetch(), $output->fetch());
    }

    public static function provideStyledRowspanValues(): iterable
    {
        yield 'plain' => ["first\nsecond", 1];
        yield 'tagged' => ["<info>first</info>\n<info>second</info>", 1];
        yield 'tagged with colspan' => ["<info>first</info>\n<info>second</info>", 2];
    }

    public function testMaximumColumnWidthIsPreserved()
    {
        $output = new BufferedOutput();
        (new Table($output))->setRows([['a', 'hidden', 'long value']])->setColumnMaxWidth(2, 4)->setOptionalColumns([1])->setMaxWidth(12)->render();
        $expected = new BufferedOutput();
        (new Table($expected))->setRows([['a', 'long value']])->setColumnMaxWidth(1, 4)->render();
        $this->assertSame($expected->fetch(), $output->fetch());
    }

    public function testColumnConfigurationAndRowsSurviveRepeatedRendering()
    {
        $output = new BufferedOutput();
        $table = (new Table($output))
            ->setRows([['a', 'hidden', 'c']])
            ->setColumnWidth(2, 5)
            ->setColumnStyle(2, 'compact')
            ->setOptionalColumns([1])
            ->setMaxWidth(12);
        $table->render();
        $narrow = $output->fetch();
        $table->render();
        $this->assertSame($narrow, $output->fetch());

        $expected = new BufferedOutput();
        (new Table($expected))->setRows([['a', 'c']])->setColumnWidth(1, 5)->setColumnStyle(1, 'compact')->render();
        $this->assertSame($expected->fetch(), $narrow);

        $table->setMaxWidth(80)->render();
        $expected = new BufferedOutput();
        (new Table($expected))->setRows([['a', 'hidden', 'c']])->setColumnWidth(2, 5)->setColumnStyle(2, 'compact')->render();
        $this->assertSame($expected->fetch(), $output->fetch());
    }

    public function testUnicodeAndFormattingDoNotCountAsExtraColumns()
    {
        $output = new BufferedOutput();
        (new Table($output))->setRows([['<info>界</info>', 'b']])->setOptionalColumns([1])->setMaxWidth(10)->render();

        $expected = new BufferedOutput();
        (new Table($expected))->setRows([['<info>界</info>', 'b']])->render();
        $this->assertSame($expected->fetch(), $output->fetch());
    }

    public function testTerminalWidthIsUsedByDefault()
    {
        $columns = getenv('COLUMNS');
        try {
            putenv('COLUMNS=5');
            $output = new BufferedOutput();
            $table = (new Table($output))->setRows([['a', 'hidden']])->setOptionalColumns([1]);
            $table->render();
            $narrow = $output->fetch();
            $this->assertStringNotContainsString('hidden', $narrow);

            putenv('COLUMNS=80');
            $table->render();
            $this->assertStringContainsString('hidden', $output->fetch());

            $table->setMaxWidth(5)->setMaxWidth(null)->render();
            $this->assertStringContainsString('hidden', $output->fetch());
        } finally {
            putenv(false === $columns ? 'COLUMNS' : 'COLUMNS='.$columns);
        }
    }

    public function testEmptyTable()
    {
        $output = new BufferedOutput();
        (new Table($output))->setOptionalColumns([0])->setMaxWidth(1)->render();
        $this->assertSame('', $output->fetch());
    }

    public function testRowsWithOnlyHiddenCells()
    {
        $output = new BufferedOutput();
        (new Table($output))->setRows([['hidden', 'a'], ['hidden']])->setOptionalColumns([0])->setMaxWidth(5)->render();
        $expected = new BufferedOutput();
        (new Table($expected))->setRows([['a'], ['']])->render();
        $this->assertSame($expected->fetch(), $output->fetch());
    }

    public function testPartialColspan()
    {
        $output = new BufferedOutput();
        (new Table($output))
            ->setHeaders(['A', 'B', 'C'])
            ->setRows([[new TableCell('abc', ['colspan' => 2]), 'c']])
            ->setOptionalColumns([0])
            ->setMaxWidth(11)
            ->render();

        $expected = new BufferedOutput();
        (new Table($expected))->setHeaders(['B', 'C'])->setRows([['abc', 'c']])->render();
        $this->assertSame($expected->fetch(), $output->fetch());
    }

    public function testHorizontalTableUsesRenderedColumnIndices()
    {
        $output = new BufferedOutput();
        (new Table($output))->setHeaders(['A', 'B'])->setRows([['a', 'b'], ['hidden', 'hidden']])->setHorizontal()->setOptionalColumns([2])->setMaxWidth(9)->render();
        $expected = new BufferedOutput();
        (new Table($expected))->setHeaders(['A', 'B'])->setRows([['a', 'b']])->setHorizontal()->render();
        $this->assertSame($expected->fetch(), $output->fetch());
    }

    public function testAppendRowCanHidePreviouslyVisibleColumns()
    {
        $stream = fopen('php://memory', 'r+');
        $sections = [];
        $output = new ConsoleSectionOutput($stream, $sections, BufferedOutput::VERBOSITY_NORMAL, true, new OutputFormatter());
        $output->setDecorated(true);
        $table = (new Table($output))->setRows([['a', 'b']])->setOptionalColumns([1])->setMaxWidth(9);
        $table->render();
        $table->appendRow(['c', 'hidden']);

        $this->assertSame("+---+\n| a |\n| c |\n+---+\n", $output->getContent());
    }

    public function testMaxWidthMustBePositive()
    {
        $this->expectException(InvalidArgumentException::class);
        (new Table(new BufferedOutput()))->setMaxWidth(0);
    }

    public function testOptionalColumnIndicesMustBeNonNegativeIntegers()
    {
        $this->expectException(InvalidArgumentException::class);
        (new Table(new BufferedOutput()))->setOptionalColumns([-1]);
    }
}
