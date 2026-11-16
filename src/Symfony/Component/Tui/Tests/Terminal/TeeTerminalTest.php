<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Terminal;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\ProgramStatus\ProgramState;
use Symfony\Component\Tui\Terminal\ProgramStatus\ProgramStatus;
use Symfony\Component\Tui\Terminal\TeeTerminal;
use Symfony\Component\Tui\Terminal\VirtualTerminal;

class TeeTerminalTest extends TestCase
{
    public function testProgramStatusIsReportedToBothTerminals()
    {
        $primary = new VirtualTerminal();
        $secondary = new VirtualTerminal();
        $terminal = new TeeTerminal($primary, $secondary);

        $terminal->setProgramStatus(new ProgramStatus(ProgramState::Done));
        $terminal->clearProgramStatus('build');

        $this->assertSame("\x1b]7501;state=done\x1b\\\x1b]7501;state=clear:id=build\x1b\\", $primary->getOutput());
        $this->assertSame($primary->getOutput(), $secondary->getOutput());
    }

    public function testTitlesArePushedAndPoppedOnBothTerminals()
    {
        $primary = new VirtualTerminal();
        $secondary = new VirtualTerminal();
        $terminal = new TeeTerminal($primary, $secondary);

        $terminal->pushTitle();
        $terminal->popTitle();

        $this->assertSame("\x1b[22;0t\x1b[23;0t", $primary->getOutput());
        $this->assertSame($primary->getOutput(), $secondary->getOutput());
    }
}
