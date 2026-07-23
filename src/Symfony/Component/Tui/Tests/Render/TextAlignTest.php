<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Render;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Style\TailwindStylesheet;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class TextAlignTest extends TestCase
{
    #[DataProvider('textAlignProvider')]
    public function testTextAlignment(string $styleClass, string $expectedVisible)
    {
        $renderer = new Renderer(new TailwindStylesheet());

        $root = new ContainerWidget();
        $text = new TextWidget('Hi');
        $text->addStyleClass($styleClass);
        $root->add($text);

        $lines = $renderer->render($root, 10, 5);

        $this->assertCount(1, $lines);
        $this->assertSame($expectedVisible, AnsiUtils::stripAnsiCodes($lines[0]));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function textAlignProvider(): iterable
    {
        yield 'left' => ['text-left', 'Hi        '];
        yield 'center' => ['text-center', '    Hi    '];
        yield 'right' => ['text-right', '        Hi'];
    }

    public function testTextCenterWithPadding()
    {
        $renderer = new Renderer(new TailwindStylesheet());

        $root = new ContainerWidget();
        $text = new TextWidget('Hi');
        $text->addStyleClass('text-center');
        $text->addStyleClass('pl-1');
        $text->addStyleClass('pr-1');
        $root->add($text);

        $lines = $renderer->render($root, 10, 5);

        $this->assertCount(1, $lines);
        $visible = AnsiUtils::stripAnsiCodes($lines[0]);
        // 10 columns total, 1 padding each side = 8 content width
        // "Hi" (2) centered in 8 content: 3 left + Hi + 3 right, then +1 padding each side
        // = 1 + 3 + 2 + 3 + 1 = 10
        $this->assertSame(' '.str_repeat(' ', 3).'Hi'.str_repeat(' ', 3).' ', $visible);
    }

    public function testTextCenterMultipleLines()
    {
        $renderer = new Renderer(new TailwindStylesheet());

        $root = new ContainerWidget();
        $text = new TextWidget("AB\nCDEF");
        $text->addStyleClass('text-center');
        $root->add($text);

        $lines = $renderer->render($root, 10, 5);

        $this->assertCount(2, $lines);
        $line1 = AnsiUtils::stripAnsiCodes($lines[0]);
        $line2 = AnsiUtils::stripAnsiCodes($lines[1]);
        // Both lines are centered as a block based on the widest line (CDEF=4).
        // Available space = 10 - 4 = 6, offset = floor(6/2) = 3.
        // "AB": 3 + AB + 5 trailing = 10
        $this->assertSame('   AB     ', $line1);
        // "CDEF": 3 + CDEF + 3 trailing = 10
        $this->assertSame('   CDEF   ', $line2);
    }

    public function testTextCenterWithColor()
    {
        $renderer = new Renderer(new TailwindStylesheet());

        $root = new ContainerWidget();
        $text = new TextWidget('Hi');
        $text->addStyleClass('text-center');
        $text->addStyleClass('text-red-500');
        $root->add($text);

        $lines = $renderer->render($root, 10, 5);

        $this->assertCount(1, $lines);
        $visible = AnsiUtils::stripAnsiCodes($lines[0]);
        $this->assertSame('    Hi    ', $visible);
        // Should contain ANSI color codes
        $this->assertNotSame($visible, $lines[0]);
    }

    public function testTextCenterMultipleLinesAlignAsBlock()
    {
        $renderer = new Renderer(new TailwindStylesheet());

        $root = new ContainerWidget();
        // Simulate multi-line content where lines have different widths
        // (like FIGlet output). All lines must shift by the same offset
        // to preserve internal alignment.
        $text = new TextWidget("X\nABC\nHello");
        $text->addStyleClass('text-center');
        $root->add($text);

        $lines = $renderer->render($root, 20, 5);

        $this->assertCount(3, $lines);

        // Widest line is "Hello" (5 chars). Available = 20 - 5 = 15, offset = 7.
        // All lines get the same 7-space left offset.
        $visibleLines = array_map(AnsiUtils::stripAnsiCodes(...), $lines);
        $this->assertSame('       X            ', $visibleLines[0]); // 7 + 1 + 12
        $this->assertSame('       ABC          ', $visibleLines[1]); // 7 + 3 + 10
        $this->assertSame('       Hello        ', $visibleLines[2]); // 7 + 5 + 8

        // Verify all lines have the same leading whitespace
        preg_match('/^(\s*)/', $visibleLines[0], $m0);
        preg_match('/^(\s*)/', $visibleLines[1], $m1);
        preg_match('/^(\s*)/', $visibleLines[2], $m2);
        $this->assertSame(\strlen($m0[1] ?? ''), \strlen($m1[1] ?? ''));
        $this->assertSame(\strlen($m1[1] ?? ''), \strlen($m2[1] ?? ''));
    }

    public function testDefaultIsLeftAligned()
    {
        $renderer = new Renderer(new TailwindStylesheet());

        $root = new ContainerWidget();
        $text = new TextWidget('Hi');
        // Force chrome application by adding background
        $text->addStyleClass('bg-blue-500');
        $root->add($text);

        $lines = $renderer->render($root, 10, 5);

        $this->assertCount(1, $lines);
        $visible = AnsiUtils::stripAnsiCodes($lines[0]);
        $this->assertSame('Hi        ', $visible);
    }
}
