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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\AnsiColorMode;
use Symfony\Component\Console\Terminal;

class TerminalTest extends TestCase
{
    private string|false $colSize;
    private string|false $lineSize;
    private string|false $ansiCon;

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
        foreach (['height', 'width', 'stty', 'kittyGraphics', 'iterm2Images'] as $name) {
            $property = new \ReflectionProperty(Terminal::class, $name);
            $property->setValue(null, null);
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

        $sttyString = shell_exec('(stty -a | grep columns) 2> NUL');
        if (!$sttyString) {
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

    #[DataProvider('provideTerminalColorEnv')]
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

    #[DataProvider('provideKittyGraphicsEnv')]
    public function testSupportsKittyGraphics(?string $termProgram, ?string $term, ?string $ghosttyResources, ?string $konsoleVersion, bool $expected)
    {
        $oriTermProgram = getenv('TERM_PROGRAM');
        $oriTerm = getenv('TERM');
        $oriGhostty = getenv('GHOSTTY_RESOURCES_DIR');
        $oriKonsole = getenv('KONSOLE_VERSION');

        try {
            putenv($termProgram ? "TERM_PROGRAM={$termProgram}" : 'TERM_PROGRAM');
            putenv($term ? "TERM={$term}" : 'TERM');
            putenv($ghosttyResources ? "GHOSTTY_RESOURCES_DIR={$ghosttyResources}" : 'GHOSTTY_RESOURCES_DIR');
            putenv($konsoleVersion ? "KONSOLE_VERSION={$konsoleVersion}" : 'KONSOLE_VERSION');

            $this->assertSame($expected, Terminal::supportsKittyGraphics());
        } finally {
            (false !== $oriTermProgram) ? putenv('TERM_PROGRAM='.$oriTermProgram) : putenv('TERM_PROGRAM');
            (false !== $oriTerm) ? putenv('TERM='.$oriTerm) : putenv('TERM');
            (false !== $oriGhostty) ? putenv('GHOSTTY_RESOURCES_DIR='.$oriGhostty) : putenv('GHOSTTY_RESOURCES_DIR');
            (false !== $oriKonsole) ? putenv('KONSOLE_VERSION='.$oriKonsole) : putenv('KONSOLE_VERSION');
            Terminal::setKittyGraphicsSupport(null);
        }
    }

    public static function provideKittyGraphicsEnv(): \Generator
    {
        // TERM_PROGRAM checks
        yield 'kitty terminal' => ['kitty', null, null, null, true];
        yield 'WezTerm terminal' => ['WezTerm', null, null, null, true];
        yield 'ghostty terminal' => ['ghostty', null, null, null, true];
        yield 'other terminal program' => ['iTerm.app', null, null, null, false];

        // TERM checks
        yield 'kitty in TERM' => [null, 'xterm-kitty', null, null, true];
        yield 'other TERM' => [null, 'xterm-256color', null, null, false];

        // GHOSTTY_RESOURCES_DIR check
        yield 'ghostty resources' => [null, null, '/some/path', null, true];

        // KONSOLE_VERSION check
        yield 'konsole' => [null, null, null, '22.12.3', true];

        // None
        yield 'no support' => [null, null, null, null, false];
    }

    #[DataProvider('provideITerm2ImagesEnv')]
    public function testSupportsITerm2Images(?string $termProgram, bool $expected)
    {
        $oriTermProgram = getenv('TERM_PROGRAM');

        try {
            putenv($termProgram ? "TERM_PROGRAM={$termProgram}" : 'TERM_PROGRAM');

            $this->assertSame($expected, Terminal::supportsITerm2Images());
        } finally {
            (false !== $oriTermProgram) ? putenv('TERM_PROGRAM='.$oriTermProgram) : putenv('TERM_PROGRAM');
            Terminal::setITerm2ImagesSupport(null);
        }
    }

    public static function provideITerm2ImagesEnv(): \Generator
    {
        yield 'iTerm.app' => ['iTerm.app', true];
        yield 'other terminal' => ['Terminal.app', false];
        yield 'kitty' => ['kitty', false];
        yield 'no terminal program' => [null, false];
    }

    public function testSupportsImageProtocol()
    {
        Terminal::setKittyGraphicsSupport(false);
        Terminal::setITerm2ImagesSupport(false);
        $this->assertFalse(Terminal::supportsImageProtocol());

        Terminal::setKittyGraphicsSupport(true);
        Terminal::setITerm2ImagesSupport(false);
        $this->assertTrue(Terminal::supportsImageProtocol());

        Terminal::setKittyGraphicsSupport(false);
        Terminal::setITerm2ImagesSupport(true);
        $this->assertTrue(Terminal::supportsImageProtocol());

        Terminal::setKittyGraphicsSupport(true);
        Terminal::setITerm2ImagesSupport(true);
        $this->assertTrue(Terminal::supportsImageProtocol());
    }

    public function testSetKittyGraphicsSupport()
    {
        Terminal::setKittyGraphicsSupport(true);
        $this->assertTrue(Terminal::supportsKittyGraphics());

        Terminal::setKittyGraphicsSupport(false);
        $this->assertFalse(Terminal::supportsKittyGraphics());

        Terminal::setKittyGraphicsSupport(null);
    }

    public function testSetITerm2ImagesSupport()
    {
        Terminal::setITerm2ImagesSupport(true);
        $this->assertTrue(Terminal::supportsITerm2Images());

        Terminal::setITerm2ImagesSupport(false);
        $this->assertFalse(Terminal::supportsITerm2Images());

        Terminal::setITerm2ImagesSupport(null);
    }
}
