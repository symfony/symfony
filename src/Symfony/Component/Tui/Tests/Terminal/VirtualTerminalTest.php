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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\VirtualTerminal;

class VirtualTerminalTest extends TestCase
{
    public function testSimulateInputForwardsKeySequences()
    {
        $terminal = new VirtualTerminal();
        $received = [];

        $terminal->start(
            static function (string $data) use (&$received) { $received[] = $data; },
            static function () {},
            static function () {},
        );

        $terminal->simulateInput('abc');

        $this->assertSame(['a', 'b', 'c'], $received);

        $terminal->stop();
    }

    public function testSimulateInputForwardsPasteContent()
    {
        $terminal = new VirtualTerminal();
        $received = [];

        $terminal->start(
            static function (string $data) use (&$received) { $received[] = $data; },
            static function () {},
            static function () {},
        );

        $terminal->simulateInput("\x1b[200~Hello World\x1b[201~");

        $this->assertSame(["\x1b[200~Hello World\x1b[201~"], $received);

        $terminal->stop();
    }

    public function testSimulateInputForwardsPasteMixedWithKeys()
    {
        $terminal = new VirtualTerminal();
        $received = [];

        $terminal->start(
            static function (string $data) use (&$received) { $received[] = $data; },
            static function () {},
            static function () {},
        );

        $terminal->simulateInput("a\x1b[200~pasted\x1b[201~b");

        $this->assertSame(['a', "\x1b[200~pasted\x1b[201~", 'b'], $received);

        $terminal->stop();
    }

    #[DataProvider('setTitleProvider')]
    public function testSetTitleSanitizesInput(string $input, string $expectedOutput)
    {
        $terminal = new VirtualTerminal();
        $terminal->setTitle($input);

        $this->assertSame($expectedOutput, $terminal->getOutput());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function setTitleProvider(): iterable
    {
        yield 'strips BEL character' => ["evil\x07injected", "\x1b]0;evilinjected\x07"];
        yield 'strips ESC character' => ["evil\x1b[31mred", "\x1b]0;evil[31mred\x07"];
        yield 'strips all control characters' => ["a\x00b\x01c\x1fd\x7fe", "\x1b]0;abcde\x07"];
        yield 'preserves unicode' => ['✓ Complete! 🎉', "\x1b]0;✓ Complete! 🎉\x07"];
    }

    public function testConsumeOutputReturnsAndClearsBuffer()
    {
        $terminal = new VirtualTerminal();
        $terminal->write('first');
        $terminal->write(' batch');

        $this->assertSame('first batch', $terminal->consumeOutput());
        $this->assertSame('', $terminal->consumeOutput());

        $terminal->write('second batch');

        $this->assertSame('second batch', $terminal->consumeOutput());
        $this->assertSame('', $terminal->getOutput());
    }

    public function testSimulateInputPasteWithMultilineContent()
    {
        $terminal = new VirtualTerminal();
        $received = [];

        $terminal->start(
            static function (string $data) use (&$received) { $received[] = $data; },
            static function () {},
            static function () {},
        );

        $terminal->simulateInput("\x1b[200~line1\nline2\nline3\x1b[201~");

        $this->assertSame(["\x1b[200~line1\nline2\nline3\x1b[201~"], $received);

        $terminal->stop();
    }
}
