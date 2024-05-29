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
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Output\StreamOutput;

class CursorTest extends TestCase
{
    /** @var resource */
    protected $stream;

    protected function setUp(): void
    {
        $this->stream = fopen('php://memory', 'r+');
    }

    protected function tearDown(): void
    {
        unset($this->stream);
    }

    public function testMoveUpOneLine()
    {
        $cursor = new Cursor($output = $this->getOutputStream());

        $cursor->moveUp();

        $this->assertEquals("\x1b[1A", $this->getOutputContent($output));
    }

    public function testMoveUpMultipleLines()
    {
        $cursor = new Cursor($output = $this->getOutputStream());

        $cursor->moveUp(12);

        $this->assertEquals("\x1b[12A", $this->getOutputContent($output));
    }

    public function testMoveDownOneLine()
    {
        $cursor = new Cursor($output = $this->getOutputStream());

        $cursor->moveDown();

        $this->assertEquals("\x1b[1B", $this->getOutputContent($output));
    }

    public function testMoveDownMultipleLines()
    {
        $cursor = new Cursor($output = $this->getOutputStream());

        $cursor->moveDown(12);

        $this->assertEquals("\x1b[12B", $this->getOutputContent($output));
    }

    public function testMoveLeftOneLine()
    {
        $cursor = new Cursor($output = $this->getOutputStream());

        $cursor->moveLeft();

        $this->assertEquals("\x1b[1D", $this->getOutputContent($output));
    }

    public function testMoveLeftMultipleLines()
    {
        $cursor = new Cursor($output = $this->getOutputStream());

        $cursor->moveLeft(12);

        $this->assertEquals("\x1b[12D", $this->getOutputContent($output));
    }

    public function testMoveRightOneLine()
    {
        $cursor = new Cursor($output = $this->getOutputStream());

        $cursor->moveRight();

        $this->assertEquals("\x1b[1C", $this->getOutputContent($output));
    }

    public function testMoveRightMultipleLines()
    {
        $cursor = new Cursor($output = $this->getOutputStream());

        $cursor->moveRight(12);

        $this->assertEquals("\x1b[12C", $this->getOutputContent($output));
    }

    public function testMoveToColumn()
    {
        $cursor = new Cursor($output = $this->getOutputStream());

        $cursor->moveToColumn(6);

        $this->assertEquals("\x1b[6G", $this->getOutputContent($output));
    }

    public function testMoveToPosition()
    {
        $cursor = new Cursor($output = $this->getOutputStream());

        $cursor->moveToPosition(18, 16);

        $this->assertEquals("\x1b[17;18H", $this->getOutputContent($output));
    }

    public function testClearLine()
    {
        $cursor = new Cursor($output = $this->getOutputStream());

        $cursor->clearLine();

        $this->assertEquals("\x1b[2K", $this->getOutputContent($output));
    }

    public function testSavePosition()
    {
        $cursor = new Cursor($output = $this->getOutputStream());

        $cursor->savePosition();

        $this->assertEquals("\x1b7", $this->getOutputContent($output));
    }

    public function testHide()
    {
        $cursor = new Cursor($output = $this->getOutputStream());

        $cursor->hide();

        $this->assertEquals("\x1b[?25l", $this->getOutputContent($output));
    }

    public function testShow()
    {
        $cursor = new Cursor($output = $this->getOutputStream());

        $cursor->show();

        $this->assertEquals("\x1b[?25h\x1b[?0c", $this->getOutputContent($output));
    }

    public function testRestorePosition()
    {
        $cursor = new Cursor($output = $this->getOutputStream());

        $cursor->restorePosition();

        $this->assertEquals("\x1b8", $this->getOutputContent($output));
    }

    public function testClearOutput()
    {
        $cursor = new Cursor($output = $this->getOutputStream());

        $cursor->clearOutput();

        $this->assertEquals("\x1b[0J", $this->getOutputContent($output));
    }

    public function testGetCurrentPosition()
    {
        $cursor = new Cursor($output = $this->getOutputStream());

        $cursor->moveToPosition(10, 10);
        $position = $cursor->getCurrentPosition();

        $this->assertEquals("\x1b[11;10H", $this->getOutputContent($output));

        $isTtySupported = (bool) @proc_open('echo 1 >/dev/null', [['file', '/dev/tty', 'r'], ['file', '/dev/tty', 'w'], ['file', '/dev/tty', 'w']], $pipes);
        $this->assertEquals($isTtySupported, '/' === \DIRECTORY_SEPARATOR && stream_isatty(\STDOUT));

        if ($isTtySupported) {
            // When tty is supported, we can't validate the exact cursor position since it depends where the cursor is when the test runs.
            // Instead we just make sure that it doesn't return 1,1
            $this->assertNotEquals([1, 1], $position);
        } else {
            $this->assertEquals([1, 1], $position);
        }
    }

    protected function getOutputContent(StreamOutput $output)
    {
        rewind($output->getStream());

        return str_replace(\PHP_EOL, "\n", stream_get_contents($output->getStream()));
    }

    protected function getOutputStream(): StreamOutput
    {
        return new StreamOutput($this->stream, StreamOutput::VERBOSITY_NORMAL);
    }
}
