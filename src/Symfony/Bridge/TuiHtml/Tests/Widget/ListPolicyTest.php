<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Render\TerminalLayoutMetrics;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Style\Density;
use Symfony\Bridge\TuiHtml\Widget\DocumentWidget;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;

class ListPolicyTest extends TestCase
{
    public function testOutsideMarkersReserveTheMeasuredGroupWidthAtEachDensity()
    {
        $cases = [
            ['<ul><li>One</li><li>Two wraps across the room</li></ul>', '', ['• One', '• Two wraps', '  across the', '  room']],
            ['<ol start="9"><li>One</li><li>Two wraps across the room</li></ol>', '', [' 9. One', '10. Two wraps', '    across the', '    room']],
            ['<ul><li>One</li><li>Two wraps across the room</li></ul>', 'li::marker{content:"LONG "}', ['LONG One', 'LONG Two wraps', '     across the', '     room']],
            ['<ul><li>One</li><li>Two wraps across the room</li></ul>', 'li{padding-left:2ch}', ['  • One', '  • Two wraps', '    across the', '    room']],
        ];
        foreach ([1, 2] as $pixels) {
            foreach ([Density::Regular, Density::Condensed] as $density) {
                foreach ($cases as [$html, $css, $expected]) {
                    $session = (new HtmlRenderer())->createSession('<style>ul,ol{padding:0;margin:0}'.$css.'</style>'.$html, new RenderingContext(128 * $pixels, 128 * $pixels));
                    $before = $session->getDocument()->saveHtml();
                    $widget = (new DocumentWidget($session, new TerminalTextMeasurer(8 * $pixels, 16 * $pixels)))->setDensity($density);
                    $lines = $widget->render(new RenderContext(16, 8));
                    $this->assertSame($expected, array_map(static fn ($line) => rtrim(AnsiUtils::stripAnsiCodes($line)), \array_slice($lines, 0, 4)));
                    $this->assertSame($before, $session->getDocument()->saveHtml());
                }
            }
        }
    }

    public function testNestedGroupsKeepIndependentWidthsAndRtlUsesLogicalStart()
    {
        $session = (new HtmlRenderer())->createSession('<style>ol{padding:0;margin:0}</style><ol start="9"><li>Outer<ol><li>Nested</li></ol></li><li>Other</li></ol>', new RenderingContext(128, 128));
        (new DocumentWidget($session))->render(new RenderContext(16, 8));
        $result = $session->render();
        $outer = $session->getDocument()->querySelector('li');
        $nested = $session->getDocument()->querySelector('ol ol li');
        $this->assertSame(8.0, $result->getBounds($outer, 'marker')[0]->x);
        $this->assertSame(32.0, $result->getBounds($nested, 'marker')[0]->x);
        $this->assertSame(24.0, $result->getBounds($nested, 'marker')[0]->width);
        $session = (new HtmlRenderer())->createSession('<ul style="direction:rtl;padding:0;margin:0"><li>One</li></ul>', new RenderingContext(128, 128));
        (new DocumentWidget($session))->render(new RenderContext(16, 8));
        $marker = $session->render()->getBounds($session->getDocument()->querySelector('li'), 'marker')[0];
        $this->assertSame(112.0, $marker->x);
        $this->assertSame(128.0, $marker->x + $marker->width);
    }

    public function testInsideNoneRetypingAndAdoptionDoNotLeaveUnusedGutters()
    {
        $session = (new HtmlRenderer())->createSession('<style>ul{padding:0;margin:0}</style><ul><li>One</li></ul>', new RenderingContext(160, 128));
        $widget = new DocumentWidget($session);
        $context = new RenderContext(20, 8);
        $item = $session->getDocument()->querySelector('li');
        $widget->render($context);
        foreach (['list-style-position:inside' => '• One', 'list-style-type:none' => 'One', 'display:block' => 'One'] as $css => $text) {
            $item->setAttribute('style', $css);
            $this->assertSame($text, rtrim(AnsiUtils::stripAnsiCodes($widget->render($context)[0])));
            $this->assertArrayNotHasKey('padding-inline-start', $session->render()->getComputedStyle($item));
        }
        $item->removeAttribute('style');
        $widget->render($context);
        $other = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main></main>');
        $other->querySelector('main')->append($other->adoptNode($item));
        $widget->render($context);
        $this->assertSame($other, $item->ownerDocument);
        $this->assertTrue($item->isConnected);
    }

    public function testGenericListItemsAndControlPolicyAreComposedWithoutLosingProperties()
    {
        $session = (new HtmlRenderer())->createSession('<style>.item,input{display:list-item;list-style-type:disc}</style><form><div class="item">Generic</div><input type="file"></form>', new RenderingContext(320, 128));
        $widget = new DocumentWidget($session);
        $lines = $widget->render(new RenderContext(40, 8));
        $this->assertSame('• Generic', rtrim(AnsiUtils::stripAnsiCodes($lines[0])));
        $style = $session->render()->getComputedStyle($session->getDocument()->querySelector('input'));
        $this->assertSame('relative', $style['position']);
        $this->assertArrayNotHasKey('padding-inline-start', $style);
        $this->assertSame(0.0, $session->render()->getBounds($session->getDocument()->querySelector('input'), 'marker')[0]->x);
        $this->assertStringContainsString('Path to a file', AnsiUtils::stripAnsiCodes(implode('', $lines)));
        $session->reset($session->getDocument()->querySelector('form'));
        $widget->render(new RenderContext(40, 8));
        $input = $session->getDocument()->querySelector('input');
        $this->assertSame(0.0, $session->render()->getBounds($input, 'marker')[0]->x);
        $input->setAttribute('type', 'text');
        $lines = $widget->render(new RenderContext(40, 8));
        $style = $session->render()->getComputedStyle($input);
        $this->assertArrayNotHasKey('padding-inline-start', $style);
        $this->assertSame(0.0, $session->render()->getBounds($input, 'marker')[0]->x);
        $this->assertNotSame('relative', $style['position'] ?? 'static');
        $this->assertStringNotContainsString('Path to a file', AnsiUtils::stripAnsiCodes(implode('', $lines)));
        $input->setAttribute('type', 'file');
        $widget->render(new RenderContext(40, 8));
        $other = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main></main>');
        $other->querySelector('main')->append($other->adoptNode($input));
        $widget->render(new RenderContext(40, 8));
        $this->assertTrue($input->isConnected);
    }

    public function testSinglePassGuttersDoNotInvalidateTheSessionAndWarmFramesReuseTheResult()
    {
        $session = (new HtmlRenderer())->createSession('<style>ul{padding:0;margin:0}</style><ul><li>One</li><li>Two</li></ul>', new RenderingContext(160, 128));
        $widget = new DocumentWidget($session);
        $invalidations = 0;
        $session->onInvalidation(static function () use (&$invalidations): void { ++$invalidations; });
        $context = new RenderContext(20, 8);
        $lines = $widget->render($context);
        $result = $session->render();
        $this->assertSame(0, $invalidations);
        $this->assertSame($lines, $widget->render($context));
        $this->assertSame($result, $session->render());
        $this->assertSame(0, $invalidations);
        $session->getDocument()->querySelector('li')->remove();
        $widget->render($context);
        $this->assertSame('• Two', rtrim(AnsiUtils::stripAnsiCodes($widget->render($context)[0])));
    }

    public function testAuthorPaddingIsAdditiveAndExplicitPoliciesRemainAuthoritative()
    {
        $html = '<style>ul{padding:0;margin:0}li{padding-inline-start:1ch}</style><ul><li>One</li></ul>';
        $session = (new HtmlRenderer())->createSession($html, new RenderingContext(160, 128));
        $widget = new DocumentWidget($session);
        $context = new RenderContext(20, 8);
        $lines = $widget->render($context);
        $this->assertSame(' • One', rtrim(AnsiUtils::stripAnsiCodes($lines[0])));
        $this->assertSame('1ch', $session->render()->getComputedStyle($session->getDocument()->querySelector('li'))['padding-inline-start']);
        $session->getDocument()->querySelector('style')->textContent = 'ul{padding:0;margin:0}';
        $widget->render($context);
        $session->setContext(new RenderingContext(160, 128, null, null, null, null, [], 'html{line-height:16px}'));
        $lines = $widget->render($context);
        $this->assertSame('One', rtrim(AnsiUtils::stripAnsiCodes($lines[0])));
        $this->assertArrayNotHasKey('padding-inline-start', $session->render()->getComputedStyle($session->getDocument()->querySelector('li')));
        $policy = new TerminalLayoutMetrics();
        $session->setContext(new RenderingContext(160, 128, null, null, $policy, null, [], 'html{line-height:16px}'));
        $lines = $widget->render($context);
        $this->assertSame($policy, $session->getContext()->layoutMetrics);
        $this->assertSame('• One', rtrim(AnsiUtils::stripAnsiCodes($lines[0])));
    }
}
