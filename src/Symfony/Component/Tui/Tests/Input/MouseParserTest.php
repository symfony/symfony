<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Input;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Event\MouseEvent;
use Symfony\Component\Tui\Input\MouseButton;
use Symfony\Component\Tui\Input\MouseEventKind;
use Symfony\Component\Tui\Input\MouseParser;

class MouseParserTest extends TestCase
{
    private MouseParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MouseParser();
    }

    #[DataProvider('provideSgrSequences')]
    public function testParseSgr(string $sequence, int $x, int $y, MouseButton $button, MouseEventKind $kind, bool $shift = false, bool $alt = false, bool $ctrl = false)
    {
        $event = $this->parser->parse($sequence);

        $this->assertInstanceOf(MouseEvent::class, $event);
        $this->assertSame($x, $event->x);
        $this->assertSame($y, $event->y);
        $this->assertSame($button, $event->button);
        $this->assertSame($kind, $event->kind);
        $this->assertSame($shift, $event->shift);
        $this->assertSame($alt, $event->alt);
        $this->assertSame($ctrl, $event->ctrl);
    }

    public static function provideSgrSequences(): iterable
    {
        yield 'left press, top-left' => ["\x1b[<0;1;1M", 0, 0, MouseButton::Left, MouseEventKind::Press];
        yield 'left release' => ["\x1b[<0;1;1m", 0, 0, MouseButton::Left, MouseEventKind::Release];
        yield 'middle press' => ["\x1b[<1;3;2M", 2, 1, MouseButton::Middle, MouseEventKind::Press];
        yield 'right press' => ["\x1b[<2;10;5M", 9, 4, MouseButton::Right, MouseEventKind::Press];
        yield 'wheel up' => ["\x1b[<64;3;3M", 2, 2, MouseButton::WheelUp, MouseEventKind::Press];
        yield 'wheel down' => ["\x1b[<65;3;3M", 2, 2, MouseButton::WheelDown, MouseEventKind::Press];
        yield 'left drag' => ["\x1b[<32;5;6M", 4, 5, MouseButton::Left, MouseEventKind::Drag];
        yield 'move, no button' => ["\x1b[<35;5;6M", 4, 5, MouseButton::None, MouseEventKind::Move];
        yield 'shift + left press' => ["\x1b[<4;1;1M", 0, 0, MouseButton::Left, MouseEventKind::Press, true];
        yield 'alt + left press' => ["\x1b[<8;1;1M", 0, 0, MouseButton::Left, MouseEventKind::Press, false, true];
        yield 'ctrl + left press' => ["\x1b[<16;1;1M", 0, 0, MouseButton::Left, MouseEventKind::Press, false, false, true];
        yield 'large coordinates' => ["\x1b[<0;240;120M", 239, 119, MouseButton::Left, MouseEventKind::Press];
    }

    public function testParseX10LeftPress()
    {
        // ESC [ M, then cb=32 (button 0), cx=33 (col 1), cy=33 (row 1), each offset by 32.
        $event = $this->parser->parse("\x1b[M".\chr(32).\chr(33).\chr(33));

        $this->assertInstanceOf(MouseEvent::class, $event);
        $this->assertSame(0, $event->x);
        $this->assertSame(0, $event->y);
        $this->assertSame(MouseButton::Left, $event->button);
        $this->assertSame(MouseEventKind::Press, $event->kind);
    }

    public function testParseX10Release()
    {
        // Button code 3 with no wheel/motion flags is a release in the X10 encoding.
        $event = $this->parser->parse("\x1b[M".\chr(32 + 3).\chr(33).\chr(33));

        $this->assertInstanceOf(MouseEvent::class, $event);
        $this->assertSame(MouseButton::None, $event->button);
        $this->assertSame(MouseEventKind::Release, $event->kind);
    }

    public function testParseX10WheelUp()
    {
        $event = $this->parser->parse("\x1b[M".\chr(32 + 64).\chr(33).\chr(33));

        $this->assertInstanceOf(MouseEvent::class, $event);
        $this->assertSame(MouseButton::WheelUp, $event->button);
        $this->assertSame(MouseEventKind::Press, $event->kind);
    }

    #[DataProvider('provideNonMouseSequences')]
    public function testNonMouseSequencesReturnNull(string $sequence)
    {
        $this->assertNull($this->parser->parse($sequence));
    }

    public static function provideNonMouseSequences(): iterable
    {
        yield 'plain character' => ['a'];
        yield 'up arrow' => ["\x1b[A"];
        yield 'bracketed paste start' => ["\x1b[200~"];
        yield 'malformed sgr (missing final)' => ["\x1b[<0;1;1"];
        yield 'malformed sgr (non-numeric)' => ["\x1b[<a;1;1M"];
        yield 'x10 too short' => ["\x1b[M".\chr(32).\chr(33)];
        yield 'empty' => [''];
        // Horizontal wheel and the additional buttons 8-11 are out of scope and
        // must be ignored rather than reported as a phantom button press.
        yield 'horizontal wheel right' => ["\x1b[<66;5;5M"];
        yield 'horizontal wheel left' => ["\x1b[<67;5;5M"];
        yield 'back button (8)' => ["\x1b[<128;5;5M"];
        yield 'forward button (9)' => ["\x1b[<129;5;5M"];
        yield 'button 10' => ["\x1b[<130;5;5M"];
        yield 'button 11' => ["\x1b[<131;5;5M"];
    }

    public function testIsWheel()
    {
        $this->assertTrue($this->parser->parse("\x1b[<64;1;1M")->isWheel());
        $this->assertFalse($this->parser->parse("\x1b[<0;1;1M")->isWheel());
    }
}
