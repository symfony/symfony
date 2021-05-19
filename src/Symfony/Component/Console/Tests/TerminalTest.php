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
            $property->setAccessible(true);
            $property->setValue(null);
        }

        // note: not resetting windowSizeChangeSignalHandlerInstalled as tied to global state of installed signal handler
        $property = new \ReflectionProperty(Terminal::class, 'windowResizeListeners');
        $property->setAccessible(true);
        $property->setValue([]);
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

    public function testWindowResizeListener()
    {
        $called = 0;
        Terminal::registerResizeListener($listener = function() use (&$called) {
            $called++;
        });

        putenv('LINES=5');
        putenv('COLUMNS=10');

        $terminal = new Terminal();

        $this->assertEquals(0, $called);

        $this->assertEquals(5, $terminal->getHeight());
        $this->assertEquals(5, $terminal->getHeight());
        $this->assertEquals(10, $terminal->getWidth());
        $this->assertEquals(10, $terminal->getWidth());

        // initial dimension extraction should have triggered call
        $this->assertEquals(1, $called);

        putenv('LINES=6');
        putenv('COLUMNS=11');

        $this->assertEquals(6, $terminal->getHeight());
        $this->assertEquals(6, $terminal->getHeight());
        $this->assertEquals(11, $terminal->getWidth());
        $this->assertEquals(11, $terminal->getWidth());

        $this->assertEquals(2, $called);

        Terminal::unregisterResizeListener($listener);

        putenv('LINES=7');

        $this->assertEquals(7, $terminal->getHeight());
        $this->assertEquals(7, $terminal->getHeight());

        // listener should not have been called anymore
        $this->assertEquals(2, $called);
    }

    public function testWindowResizeListenerOnSignal()
    {
        if (!\extension_loaded('pcntl')) {
            $this->markTestSkipped("Requires pcntl extension");
        }

        $this->assertNotFalse($pid = \getmypid());
        $this->assertTrue(Terminal::installWindowResizeSignalHandler(true, false));

        $called = 0;
        Terminal::registerResizeListener(function() use (&$called) {
            $called++;
        });

        putenv('LINES=5');
        putenv('COLUMNS=10');

        $terminal = new Terminal();

        $this->assertEquals(10, $terminal->getWidth());
        $this->assertEquals(5, $terminal->getHeight());
        $this->assertEquals(1, $called);

        // sending signal without changing dimensions
        $this->assertTrue(\posix_kill($pid, \SIGWINCH));
        $this->assertTrue(\pcntl_signal_dispatch());

        $this->assertEquals(1, $called);

        // change dimensions and trigger signal
        putenv('LINES=6');
        putenv('COLUMNS=11');
        $this->assertTrue(\posix_kill($pid, \SIGWINCH));
        $this->assertTrue(\pcntl_signal_dispatch());

        $this->assertEquals(2, $called);
    }

    public function testWindowResizeListenerOnAsyncSignal()
    {
        if (!\extension_loaded('pcntl')) {
            $this->markTestSkipped("Requires pcntl extension");
        }

        $this->assertNotFalse($pid = \getmypid());
        $this->assertTrue(Terminal::installWindowResizeSignalHandler(true, true));

        $called = 0;
        Terminal::registerResizeListener(function() use (&$called) {
            $called++;
        });

        putenv('LINES=5');
        putenv('COLUMNS=10');

        $terminal = new Terminal();

        $this->assertEquals(10, $terminal->getWidth());
        $this->assertEquals(5, $terminal->getHeight());
        $this->assertEquals(1, $called);

        putenv('LINES=6');
        putenv('COLUMNS=11');
        $this->assertTrue(\posix_kill($pid, \SIGWINCH));

        $this->assertEquals(2, $called);
    }
}
