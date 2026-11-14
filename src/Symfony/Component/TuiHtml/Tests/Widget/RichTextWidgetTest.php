<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Widget;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Event\LiveRegionChangedEvent;
use Symfony\Component\TuiHtml\Internal\StyleBridge;
use Symfony\Component\TuiHtml\Render\RenderContext;
use Symfony\Component\TuiHtml\Style\Padding;
use Symfony\Component\TuiHtml\Style\Style;
use Symfony\Component\TuiHtml\Style\TextAlign;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\RichTextWidget;
use Symfony\Component\TuiHtml\Widget\Text\InlineFormat;
use Symfony\Component\TuiHtml\Widget\Text\InlineNode;

final class RichTextWidgetTest extends TestCase
{
    public function testTheSameFormatsSetAgainDoNotDrawItAgain()
    {
        $widget = new RichTextWidget([InlineNode::text('a '), InlineNode::element('b', [], [InlineNode::text('bold')])]);
        $widget->setInlineFormats(new InlineFormat(new Style(null, null, null, 'red')), [0 => new InlineFormat(new Style(null, null, null, null, true))]);
        $widget->render(new RenderContext(20, 2));
        $revision = $widget->getRenderRevision();
        $widget->setInlineFormats(new InlineFormat(new Style(null, null, null, 'red')), [0 => new InlineFormat(new Style(null, null, null, null, true))]);
        $this->assertSame($revision, $widget->getRenderRevision(), 'Other objects of the same values, like a stylesheet restyling the page.');
        StyleBridge::set($widget, new Style(new Padding(1, 1, 1, 1)));
        $revision = $widget->getRenderRevision();
        StyleBridge::set($widget, new Style(new Padding(1, 1, 1, 1)));
        $this->assertSame($revision, $widget->getRenderRevision(), 'An equal style either.');
        $widget->setInlineFormats(new InlineFormat(new Style(null, null, null, 'red')), [0 => new InlineFormat(new Style(null, null, null, null, null, null, true))]);
        $this->assertNotSame($revision, $widget->getRenderRevision());
    }

    public function testWhitespaceCollapsesAcrossRuns()
    {
        $widget = new RichTextWidget([InlineNode::text("  Hello \n "), InlineNode::element('b', [], [InlineNode::text(' big ')]), InlineNode::text(' world  ')]);
        $this->assertSame('Hello big world', $widget->getText());
        $widget = new RichTextWidget([InlineNode::text('a'), InlineNode::element('b', [], [InlineNode::text(' b')])]);
        $widget->setInlineFormats(new InlineFormat(), [0 => new InlineFormat(new Style(null, null, null, null, true))]);
        $this->assertSame("a\x1b[1m b\x1b[22m", $widget->getText(), 'A space inside the element is in it.');
        $widget = new RichTextWidget([InlineNode::text('a '), InlineNode::element('b', [], [InlineNode::text(' b')])]);
        $widget->setInlineFormats(new InlineFormat(), [0 => new InlineFormat(new Style(null, null, null, null, true))]);
        $this->assertSame("a \x1b[1mb\x1b[22m", $widget->getText(), 'Spaces on both sides collapse into the first.');
    }

    #[RequiresPhpExtension('intl')]
    public function testTheMarkerIsIsolatedFromTheText()
    {
        // Logically "טו. 15 is x": the period stays after the Hebrew number, not with the digits after it
        $widget = new RichTextWidget([InlineNode::text('15 is x')]);
        $widget->setInlineFormats(new InlineFormat(null, false, null, null, null, 'normal', null, 0, 0, 0, null, null, 0, false, true, false, 8, [0 => 0, 1 => 0, 2 => 0, 3 => 0], null, null, 0, null, 'auto', 'טו. '), []);
        $this->assertSame(['וט. 15 is x'], $widget->render(new RenderContext(20, 2)));
    }

    public function testRubyAnnotationsAreDrawnOverTheirBases()
    {
        $ruby = static fn (string $base, string $annotation) => InlineNode::element('ruby', [], [InlineNode::text($base), InlineNode::element('rp', [], [InlineNode::text('(')]), InlineNode::element('rt', [], [InlineNode::text($annotation)]), InlineNode::element('rp', [], [InlineNode::text(')')])]);
        $formats = static fn (InlineFormat $rt) => [0 => new InlineFormat(null, false, null, null, null, 'normal', null, 0, 0, 0, null, null, 0, false, true, false, 8, [0 => 0, 1 => 0, 2 => 0, 3 => 0], null, null, 0, null, 'auto', null, 0.0, false, false, null, false, false, '-', [], [0 => 0, 1 => 0], null, null, false, null, null, null, 'ruby'), 1 => new InlineFormat(null, true), 2 => $rt, 3 => new InlineFormat(null, true)];
        $widget = new RichTextWidget([InlineNode::text('a '), $ruby('東', 'とう'), InlineNode::text(' b')]);
        $widget->setInlineFormats(new InlineFormat(), $formats(new InlineFormat(null, false, null, null, null, 'normal', null, 0, 0, 0, null, null, 0, false, true, false, 8, [0 => 0, 1 => 0, 2 => 0, 3 => 0], null, null, 0, null, 'auto', null, 0.0, false, false, null, false, false, '-', [], [0 => 0, 1 => 0], null, null, false, null, null, null, 'ruby-text')));
        $this->assertSame(['  とう', 'a  東  b'], $widget->render(new RenderContext(20, 5)), 'The base is as wide as its annotation, centered.');
        $this->assertSame('a 東(とう) b', $widget->getPlainText());
        $widget->setInlineFormats(new InlineFormat(), $formats(new InlineFormat(null, false, null, null, null, 'normal', null, 0, 0, 0, null, null, 0, false, true, false, 8, [0 => 0, 1 => 0, 2 => 0, 3 => 0], null, null, 0, null, 'auto', null, 0.0, false, false, null, false, false, '-', [], [0 => 0, 1 => 0], null, null, false, null, null, null, 'ruby-text', true, 'start')));
        $this->assertSame(['a 東   b', '  とう'], $widget->render(new RenderContext(20, 5)), 'Under the base, at its start.');
        $widget = new RichTextWidget([InlineNode::text('one two '), $ruby('漢字', 'kanji')]);
        $widget->setInlineFormats(new InlineFormat(), $formats(new InlineFormat(null, false, null, null, null, 'normal', null, 0, 0, 0, null, null, 0, false, true, false, 8, [0 => 0, 1 => 0, 2 => 0, 3 => 0], null, null, 0, null, 'auto', null, 0.0, false, false, null, false, false, '-', [], [0 => 0, 1 => 0], null, null, false, null, null, null, 'ruby-text')));
        $this->assertSame(['one two', 'kanji', '漢字'], array_map(rtrim(...), $widget->render(new RenderContext(9, 5))), 'The base is kept on one line, its annotation over it on the next.');
        $widget = new RichTextWidget([$ruby('東', 'とう')], true);
        $widget->setInlineFormats(new InlineFormat(), $formats(new InlineFormat(null, false, null, null, null, 'normal', null, 0, 0, 0, null, null, 0, false, true, false, 8, [0 => 0, 1 => 0, 2 => 0, 3 => 0], null, null, 0, null, 'auto', null, 0.0, false, false, null, false, false, '-', [], [0 => 0, 1 => 0], null, null, false, null, null, null, 'ruby-text')));
        $this->assertSame(['東(とう)'], $widget->render(new RenderContext(20, 1)), 'A line that cannot have a row over it: in parentheses.');
    }

    public function testRubyAnnotationsFollowTheAlignedBases()
    {
        $widget = new RichTextWidget([InlineNode::text('a '), InlineNode::element('ruby', [], [InlineNode::text('東'), InlineNode::element('rt', [], [InlineNode::text('to')])]), InlineNode::text(' b c'), InlineNode::element('br'), InlineNode::text('longer line here')]);
        $widget->setInlineFormats(new InlineFormat(), [0 => new InlineFormat(null, false, null, null, null, 'normal', null, 0, 0, 0, null, null, 0, false, true, false, 8, [0 => 0, 1 => 0, 2 => 0, 3 => 0], null, null, 0, null, 'auto', null, 0.0, false, false, null, false, false, '-', [], [0 => 0, 1 => 0], null, null, false, null, null, null, 'ruby'), 1 => new InlineFormat(null, false, null, null, null, 'normal', null, 0, 0, 0, null, null, 0, false, true, false, 8, [0 => 0, 1 => 0, 2 => 0, 3 => 0], null, null, 0, null, 'auto', null, 0.0, false, false, null, false, false, '-', [], [0 => 0, 1 => 0], null, null, false, null, null, null, 'ruby-text')]);
        $lines = $widget->render(new RenderContext(20, 5));
        $this->assertSame(['      to', '    a 東 b c', 'longer line here'], $widget->alignLines($lines, TextAlign::Center, 20));
        $this->assertSame(['          to', '        a 東 b c', 'longer line here'], $widget->alignLines($lines, TextAlign::Right, 20));
        // Justified, the base moves with the spaces before it, and its annotation with it
        $widget = new RichTextWidget([InlineNode::text('a b '), InlineNode::element('ruby', [], [InlineNode::text('東'), InlineNode::element('rt', [], [InlineNode::text('to')])]), InlineNode::text(' c d e f g h')]);
        $widget->setInlineFormats(new InlineFormat(), [0 => new InlineFormat(null, false, null, null, null, 'normal', null, 0, 0, 0, null, null, 0, false, true, false, 8, [0 => 0, 1 => 0, 2 => 0, 3 => 0], null, null, 0, null, 'auto', null, 0.0, false, false, null, false, false, '-', [], [0 => 0, 1 => 0], null, null, false, null, null, null, 'ruby'), 1 => new InlineFormat(null, false, null, null, null, 'normal', null, 0, 0, 0, null, null, 0, false, true, false, 8, [0 => 0, 1 => 0, 2 => 0, 3 => 0], null, null, 0, null, 'auto', null, 0.0, false, false, null, false, false, '-', [], [0 => 0, 1 => 0], null, null, false, null, null, null, 'ruby-text')]);
        $lines = $widget->alignLines($widget->render(new RenderContext(14, 5)), TextAlign::Justify, 14);
        $this->assertSame(14, AnsiUtils::visibleWidth($lines[1]));
        $this->assertSame(mb_strwidth(mb_strstr($lines[1], '東', true)), \strlen($lines[0]) - \strlen(ltrim($lines[0])));
    }

    public function testAMarkerHasNoControlBytes()
    {
        $widget = new RichTextWidget([InlineNode::text('item')]);
        $widget->setInlineFormats(new InlineFormat(null, false, null, null, null, 'normal', null, 0, 0, 0, null, null, 0, false, true, false, 8, [0 => 0, 1 => 0, 2 => 0, 3 => 0], null, null, 0, null, 'auto', "\x1b]0;title\x07• "), []);
        $this->assertSame(']0;title• ', $widget->getMarker());
        $this->assertStringNotContainsString("\x1b", implode('', $widget->render(new RenderContext(20, 2))));
    }

    public function testFormats()
    {
        $widget = new RichTextWidget([InlineNode::text('a '), InlineNode::element('b', [], [InlineNode::text('bold')]), InlineNode::element('span', [], [InlineNode::element('i', [], [InlineNode::text('hidden')])]), InlineNode::text(' end')]);
        $widget->setInlineFormats(new InlineFormat(null, false, '→ ', null, 'uppercase'), [0 => new InlineFormat(new Style(null, null, null, null, true), false, null, '!', 'uppercase'), 1 => new InlineFormat(null, true)]);
        $this->assertSame("→ A \x1b[1mBOLD\x1b[22m\x1b[1m!\x1b[22m END", $widget->getText(), 'The space before an element is outside it, like in CSS.');
        $this->assertSame('→ A BOLD! END', $widget->getPlainText());
    }

    public function testPreservesWhitespace()
    {
        $widget = new RichTextWidget([InlineNode::text("  a\n    b")]);
        $widget->setInlineFormats(new InlineFormat(null, false, null, null, null, 'pre'), []);
        $this->assertSame("  a\n    b", $widget->getText());
        $widget->setInlineFormats(new InlineFormat(null, false, null, null, null, 'pre-line'), []);
        $this->assertSame(" a\n b", $widget->getText());
    }

    public function testNowrapTruncates()
    {
        $tui = new Tui(null, $terminal = new VirtualTerminal(10, 3));
        $tui->add($widget = new RichTextWidget([InlineNode::text('a long line of text')]));
        $widget->setInlineFormats(new InlineFormat(null, false, null, null, null, 'nowrap'), []);
        $tui->start();
        $tui->processRender();
        $this->assertStringNotContainsString('text', $terminal->getOutput());
    }

    public function testLineGapAndLetterSpacing()
    {
        $widget = new RichTextWidget([InlineNode::text('ab cd')]);
        $widget->setInlineFormats(new InlineFormat(null, false, null, null, null, 'normal', null, 0, 1, 1), []);
        // Like CSS: after every character, the space included
        $this->assertSame('a b   c d', $widget->getPlainText());
        $lines = array_map(static fn (string $line) => rtrim(AnsiUtils::stripAnsiCodes($line)), $widget->render(new RenderContext(4, 10)));
        $this->assertSame(['a b', '', 'c d'], $lines, 'A blank row between the lines, none after the last.');
    }

    public function testBlockLink()
    {
        $widget = new RichTextWidget([InlineNode::text('Docs')]);
        $widget->setLink('https://symfony.com/doc');
        $this->assertSame("\x1b]8;;https://symfony.com/doc\x07Docs\x1b]8;;\x07", $widget->getText());
        $this->assertSame('Docs', $widget->getPlainText());
        $widget->setLink('javascript:alert(1)');
        $this->assertNull($widget->getLink(), 'Only safe schemes are kept.');
        $this->assertSame('Docs', $widget->getText());
    }

    public function testLinksAndBreaks()
    {
        $widget = new RichTextWidget([InlineNode::element('a', ['href' => 'https://symfony.com'], [InlineNode::text('Symfony')]), InlineNode::element('br'), InlineNode::element('a', ['href' => 'javascript:alert(1)'], [InlineNode::text('nope')])]);
        // Each link with an id, which says where it is in the frame; one the terminal cannot follow is marked for the
        // renderer, which takes the mark out, not a terminal link
        $id = RichTextWidget::linkId($widget, 0);
        $this->assertSame("\x1b]8;id={$id};https://symfony.com\x07Symfony\x1b]8;;\x07\n\x1b]8;".RichTextWidget::LINK_MARK.':id='.RichTextWidget::linkId($widget, 1).";-\x07nope\x1b]8;".RichTextWidget::LINK_MARK.";\x07", $widget->getText());
        $this->assertStringNotContainsString('javascript', $widget->getText());
        $this->assertSame("Symfony\nnope", $widget->getPlainText());
    }

    public function testStripsControlBytes()
    {
        $widget = new RichTextWidget([InlineNode::text("evil\x1b[31m")]);
        $this->assertSame('evil[31m', $widget->getText());
    }

    public function testAnnouncesContentChanges()
    {
        $widget = new RichTextWidget([InlineNode::text('Ready')], false, 'assertive');
        $events = [];
        $widget->on(LiveRegionChangedEvent::class, static function (LiveRegionChangedEvent $event) use (&$events) {
            $events[] = $event;
        });
        $widget->setText('Saving');
        $widget->setText('Saving');
        $widget->setInlineFormats(new InlineFormat(null, false, null, null, 'uppercase'), []);
        $this->assertCount(1, $events, 'Formatting and unchanged text announce nothing.');
        $this->assertSame('Ready', $events[0]->getPreviousText());
        $this->assertSame('Saving', $events[0]->getText());
        $this->assertTrue($events[0]->isAssertive());
        $this->assertSame('assertive', $widget->getPoliteness());
    }

    public function testNotLiveByDefault()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 5));
        $tui->add($widget = new RichTextWidget([InlineNode::text('a')]));
        $texts = [];
        $tui->addListener(static function (LiveRegionChangedEvent $event) use (&$texts) {
            $texts[] = $event->getText();
        });
        $widget->setText('b');
        $this->assertSame([], $texts);
        $this->assertNull($widget->getPoliteness());
    }

    public function testEllipsis()
    {
        $tui = new Tui(null, $terminal = new VirtualTerminal(10, 3));
        $tui->add($clipped = new RichTextWidget([InlineNode::text('clipped line of text')]));
        $tui->add($marked = new RichTextWidget([InlineNode::text('marked line of text')]));
        $clipped->setInlineFormats(new InlineFormat(null, false, null, null, null, 'nowrap'), []);
        $marked->setInlineFormats(new InlineFormat(null, false, null, null, null, 'nowrap', '…'), []);
        $tui->start();
        $tui->processRender();
        $screen = new \Symfony\Component\Tui\Terminal\ScreenBuffer(10, 3);
        $screen->write($terminal->getOutput());
        $this->assertSame(['clipped li', 'marked li…'], \array_slice(array_map('rtrim', explode("\n", $screen->getScreen())), 0, 2), 'Clipped by default, like CSS.');
    }
}
