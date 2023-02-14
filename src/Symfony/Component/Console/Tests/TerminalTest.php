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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\AnsiColorMode;
use Symfony\Component\Console\Terminal;

class TerminalTest extends TestCase
{
    private $colSize;
    private $lineSize;
    private $ansiCon;

    protected function setUp(): void
    {
        $this->colSize = getenv('COLUMNS');
        $this->lineSize = getenv('LINES');
        $this->ansiCon = getenv('ANSICON');
        $this->resetStatics();
    }

    protected function tearDown(): void
    {
        putenv($this->colSize ? 'COLUMNS='.$this->colSize : 'COLUMNS');
        putenv($this->lineSize ? 'LINES' : 'LINES='.$this->lineSize);
        putenv($this->ansiCon ? 'ANSICON='.$this->ansiCon : 'ANSICON');
        $this->resetStatics();
    }

    private function resetStatics()
    {
        foreach (['height', 'width', 'stty'] as $name) {
            $property = new \ReflectionProperty(Terminal::class, $name);
            $property->setValue(null);
        }
    }

    public function test()
    {
        putenv('COLUMNS=100');
        putenv('LINES=50');
        $terminal = new Terminal();
        $this->assertSame(100, $terminal->getWidth());
        $this->assertSame(50, $terminal->getHeight());

        putenv('COLUMNS=120');
        putenv('LINES=60');
        $terminal = new Terminal();
        $this->assertSame(120, $terminal->getWidth());
        $this->assertSame(60, $terminal->getHeight());
    }

    public function testZeroValues()
    {
        putenv('COLUMNS=0');
        putenv('LINES=0');

        $terminal = new Terminal();

        $this->assertSame(0, $terminal->getWidth());
        $this->assertSame(0, $terminal->getHeight());
    }

    public function testSttyOnWindows()
    {
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Must be on windows');
        }

        $sttyString = exec('(stty -a | grep columns) 2>&1', $output, $exitcode);
        if (0 !== $exitcode) {
            $this->markTestSkipped('Must have stty support');
        }

        $matches = [];
        if (0 === preg_match('/columns.(\d+)/i', $sttyString, $matches)) {
            $this->fail('Could not determine existing stty columns');
        }

        putenv('COLUMNS');
        putenv('LINES');
        putenv('ANSICON');

        $terminal = new Terminal();
        $this->assertSame((int) $matches[1], $terminal->getWidth());
    }

    /**
     * @dataProvider provideTerminalColorEnv
     */
    public function testGetColorMode(?string $testColorTerm, ?string $testTerm, AnsiColorMode $expected)
    {
        $oriColorTerm = getenv('COLORTERM');
        $oriTerm = getenv('TERM');

        try {
            putenv($testColorTerm ? "COLORTERM={$testColorTerm}" : 'COLORTERM');
            putenv($testTerm ? "TERM={$testTerm}" : 'TERM');

            $this->assertSame($expected, Terminal::getColorMode());
        } finally {
            (false !== $oriColorTerm) ? putenv('COLORTERM='.$oriColorTerm) : putenv('COLORTERM');
            (false !== $oriTerm) ? putenv('TERM='.$oriTerm) : putenv('TERM');
            Terminal::setColorMode(null);
        }
    }

    public static function provideTerminalColorEnv(): \Generator
    {
        yield ['truecolor', null, AnsiColorMode::Ansi24];
        yield ['TRUECOLOR', null, AnsiColorMode::Ansi24];
        yield ['somethingLike256Color', null, AnsiColorMode::Ansi8];
        yield [null, 'xterm-truecolor', AnsiColorMode::Ansi24];
        yield [null, 'xterm-TRUECOLOR', AnsiColorMode::Ansi24];
        yield [null, 'xterm-256color', AnsiColorMode::Ansi8];
        yield [null, 'xterm-256COLOR', AnsiColorMode::Ansi8];
        yield [null, null, Terminal::DEFAULT_COLOR_MODE];
    }

    public function testSetColorMode()
    {
        $oriColorTerm = getenv('COLORTERM');
        $oriTerm = getenv('TERM');

        try {
            putenv('COLORTERM');
            putenv('TERM');
            $this->assertSame(Terminal::DEFAULT_COLOR_MODE, Terminal::getColorMode());

            putenv('COLORTERM=256color');
            $this->assertSame(Terminal::DEFAULT_COLOR_MODE, Terminal::getColorMode()); // Terminal color mode is cached at first call. Terminal cannot change during execution.

            Terminal::setColorMode(AnsiColorMode::Ansi24); // Force change by user.
            $this->assertSame(AnsiColorMode::Ansi24, Terminal::getColorMode());
        } finally {
            (false !== $oriColorTerm) ? putenv('COLORTERM='.$oriColorTerm) : putenv('COLORTERM');
            (false !== $oriTerm) ? putenv('TERM='.$oriTerm) : putenv('TERM');
            Terminal::setColorMode(null);
        }
    }
}
