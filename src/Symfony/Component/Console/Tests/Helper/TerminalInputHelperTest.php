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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

class TerminalInputHelperTest extends TestCase
{
    /**
     * @group tty
     */
    public function testFinishFallsBackToSttySaneWhenInitialStateIsInvalid()
    {
        if (!Terminal::hasSttyAvailable()) {
            $this->markTestSkipped('stty not available');
        }

        $previousSttyMode = shell_exec('stty -g');

        $p = new Process(['php', __DIR__.'/../Fixtures/terminal_input_helper_bad_state.php']);
        try {
            $p->setTty(true);
            $p->run();
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), '/dev/tty')) {
                $this->markTestSkipped('/dev/tty is not read/writable in this environment.');
            }

            throw $e;
        }

        // Tokenize instead of matching substrings: "-echo" is also a substring of the
        // unrelated flags "-echonl" and "-echoprt", which are off by default.
        $flags = preg_split('/\s+/', trim(shell_exec('stty -a')));

        // Restore the terminal to how it was before running the test, now that we have read
        // the state left by the fixture script.
        shell_exec('stty '.trim($previousSttyMode).' 2>/dev/null || stty sane');

        $this->assertSame(0, $p->getExitCode());
        $this->assertContains('echo', $flags);
        $this->assertContains('icanon', $flags);
    }
}
