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
use Symfony\Component\Tui\Terminal\TeeTerminal;
use Symfony\Component\Tui\Terminal\VirtualTerminal;

class BellTest extends TestCase
{
    public function testBellWritesBelCharacter()
    {
        $terminal = new VirtualTerminal();
        $terminal->bell();

        $this->assertSame("\x07", $terminal->getOutput());
    }

    public function testBellOnTeeTerminalWritesToBothTerminals()
    {
        $primary = new VirtualTerminal();
        $secondary = new VirtualTerminal();
        $tee = new TeeTerminal($primary, $secondary);

        $tee->bell();

        $this->assertSame("\x07", $primary->getOutput());
        $this->assertSame("\x07", $secondary->getOutput());
    }
}
