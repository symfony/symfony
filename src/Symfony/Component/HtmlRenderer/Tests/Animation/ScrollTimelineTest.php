<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Animation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Animation\ScrollTimeline;
use Symfony\Component\HtmlRenderer\Animation\Timeline;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

class ScrollTimelineTest extends TestCase
{
    public function testFractionalScrollRangesAndInactiveFittingContent()
    {
        self::assertSame(.25, ScrollTimeline::scroll(7.625, 30.5, 61.0, [['normal', 0.0], ['normal', 100.0]]));
        self::assertNull(ScrollTimeline::scroll(0, 30.5, 30.5, [['normal', 0.0], ['normal', 100.0]]));
        self::assertEqualsWithDelta(11 / 12, ScrollTimeline::scroll(75, 50, 150, [['normal', 20.0], ['normal', 80.0]]), .000001);
    }

    public function testSelfNearestAndRootUseExplicitLayoutMetricsAndDoNotRequestClockFrames()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@keyframes fade{from{opacity:0}to{opacity:1}}html{overflow:hidden}#scroller{overflow:auto}.animated{opacity:0;animation:fade linear}#self{overflow:hidden;animation-timeline:scroll(self)}#nearest{animation-timeline:scroll()}#root{animation-timeline:scroll(root)}</style><div id="scroller"><p class="animated" id="self">Self</p><p class="animated" id="nearest">Nearest</p><p class="animated" id="root">Root</p></div>');
        $containers = new \SplObjectStorage();
        $containers[$document->documentElement] = ['scroll' => ['client-height' => 30.5, 'content-height' => 61.0, 'offset-y' => 7.625]];
        $containers[$document->getElementById('scroller')] = ['scroll' => ['client-height' => 30.5, 'content-height' => 61.0, 'offset-y' => 15.25]];
        $containers[$document->getElementById('self')] = ['scroll' => ['client-height' => 30.5, 'content-height' => 61.0, 'offset-y' => 30.5]];
        $timeline = new Timeline();
        $resolver = new StyleResolver();
        $styles = $resolver->resolve($document, 400, 200, [], $timeline, 1000, $containers);
        self::assertSame('1', $styles[$document->getElementById('self')]['opacity']);
        self::assertSame('0.5', $styles[$document->getElementById('nearest')]['opacity']);
        self::assertSame('0.25', $styles[$document->getElementById('root')]['opacity']);
        self::assertFalse($timeline->hasPendingFrames(1000));
    }

    public function testHeadlessOverflowActivatesAnInheritedCustomPropertyAndFittingContentDoesNot()
    {
        foreach (['hidden' => '32px', 'auto' => '32px', 'visible' => '32px'] as $overflow => $height) {
            $session = new HtmlRenderer()->createSession('<!DOCTYPE html><style>@keyframes more{from,to{--More:block}}#box{overflow:'.$overflow.';height:'.$height.';--More:none;animation:more linear;animation-timeline:scroll(self)}#more{display:var(--More)}</style><div id="box"><div style="height:64px">Content</div><p id="more">More</p></div>', new RenderingContext(200, 160));
            $result = $session->render();
            self::assertSame('visible' === $overflow ? 'none' : 'block', $result->getComputedStyle($result->getDocument()->getElementById('more'))['display']);
            self::assertFalse($session->isAnimating());
        }
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><style>@keyframes more{from,to{--More:block}}#box{overflow:auto;max-height:64px;--More:none;animation:more linear;animation-timeline:scroll(self)}#more{display:var(--More)}</style><div id="box"><div style="height:16px">Content</div><p id="more">More</p></div>', new RenderingContext(200, 160));
        $result = $session->render();
        self::assertSame('none', $result->getComputedStyle($result->getDocument()->getElementById('more'))['display']);
    }

    public function testScrollingChangesProgressWithoutAdvancingTime()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><style>@keyframes fade{from{opacity:0}to{opacity:1}}#box{height:30.5px;overflow:auto;animation:fade linear;animation-timeline:scroll(self)}</style><div id="box"><div style="height:61px">Content</div></div>', new RenderingContext(200, 160));
        $first = $session->render();
        $box = $first->getDocument()->getElementById('box');
        self::assertSame('0', $first->getComputedStyle($box)['opacity']);
        $session->scroll($box, 0, 7.625);
        self::assertSame('0.25', $session->render()->getComputedStyle($box)['opacity']);
        $session->advance(100);
        self::assertSame('0.25', $session->render()->getComputedStyle($box)['opacity']);
        self::assertFalse($session->isAnimating());
    }

    public function testLayoutDrivenAnimationsCannotReuseATimeOnlyPreparedStage()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@keyframes fade{from{opacity:0}to{opacity:1}}p{overflow:hidden;animation:fade linear;animation-timeline:scroll(self)}</style><p>Target</p>');
        $timeline = new Timeline();
        $resolver = new StyleResolver();
        $styles = $resolver->resolve($document, 400, 200, [], $timeline, 0, null, null, null, [], null, true);
        self::assertTrue($timeline->hasScrollAnimations());
        self::assertNotEmpty($styles);
        $this->expectException(\Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException::class);
        $resolver->resolvePrepared($timeline, 1);
    }

    public function testAnonymousAndScopedNamedViewUseSubjectGeometryWithoutClockFrames()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@keyframes fade{from{opacity:0}to{opacity:1}}#wrap{timeline-scope:--item}#scroll{overflow:auto}#subject{view-timeline:--item block}p{opacity:0;animation:fade linear both;animation-timeline:view()}#outside{animation-timeline:--item}</style><div id="wrap"><div id="scroll"><p id="subject">Subject</p></div><p id="outside">Outside</p></div>');
        $containers = new \SplObjectStorage();
        $containers[$document->getElementById('scroll')] = ['scroll' => ['client-height' => 40.0, 'content-height' => 100.0, 'offset-y' => 20.0, 'port-y' => 0.0]];
        $containers[$document->getElementById('subject')] = ['bounds' => [0.0, 20.0, 40.0, 20.0]];
        $timeline = new Timeline();
        $resolver = new StyleResolver();
        $styles = $resolver->resolve($document, 400, 200, [], $timeline, 0, $containers);
        self::assertEqualsWithDelta(1 / 3, (float) $styles[$document->getElementById('subject')]['opacity'], .0001);
        self::assertSame($styles[$document->getElementById('subject')]['opacity'], $styles[$document->getElementById('outside')]['opacity']);
        self::assertFalse($timeline->hasPendingFrames(0));
        $document->querySelector('style')->textContent .= '#wrap{timeline-scope:none}';
        $styles = $resolver->resolve($document, 400, 200, [], $timeline, 0, $containers);
        self::assertSame('0', $styles[$document->getElementById('outside')]['opacity']);
    }

    public function testNamedTimelineDefinitionsStayInsideTheirNativeTree()
    {
        $attribute = \Symfony\Component\HtmlRenderer\Css\Selector::TREE_ATTRIBUTE;
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@keyframes fade{from{opacity:.2}to{opacity:.8}}#wrap{timeline-scope:all}#scroll{scroll-timeline:--page block;overflow:auto}#target{opacity:.1;animation:fade linear both;animation-timeline:--page}</style><div id="wrap"><div id="scroll"></div><div '.$attribute.'="1"><p id="target" '.$attribute.'="1">Target</p></div></div>');
        $containers = new \SplObjectStorage();
        $containers[$document->getElementById('scroll')] = ['scroll' => ['client-height' => 40.0, 'content-height' => 100.0, 'offset-y' => 30.0]];
        $resolver = new StyleResolver();
        $timeline = new Timeline();
        $styles = $resolver->resolve($document, 400, 200, [], $timeline, 0, $containers);
        self::assertSame('.1', $styles[$document->getElementById('target')]['opacity']);
        self::assertFalse($timeline->hasPendingFrames(0));
        $document->getElementById('target')->removeAttribute($attribute);
        $document->getElementById('target')->parentElement->removeAttribute($attribute);
        $styles = $resolver->resolve($document, 400, 200, [], $timeline, 0, $containers);
        self::assertSame('0.5', $styles[$document->getElementById('target')]['opacity']);
        $document->getElementById('scroll')->remove();
        $styles = $resolver->resolve($document, 400, 200, [], $timeline, 0, $containers);
        self::assertSame('.1', $styles[$document->getElementById('target')]['opacity']);
    }

    public function testViewInsetsUseSharedLengthResolutionAndChangedDeviceMetrics()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@keyframes fade{from{opacity:0}to{opacity:1}}#scroll{overflow:auto}#subject{font-size:20px;opacity:0;animation:fade linear both;animation-timeline:view(block calc(1em + 1in) 10%)}</style><div id="scroll"><p id="subject">Subject</p></div>');
        $containers = new \SplObjectStorage();
        $containers[$document->getElementById('scroll')] = ['scroll' => ['client-height' => 200.0, 'content-height' => 600.0, 'offset-y' => 150.0, 'port-y' => 0.0]];
        $containers[$document->getElementById('subject')] = ['style' => ['font-size' => '20px'], 'bounds' => [0.0, 50.0, 100.0, 40.0]];
        $styles = new StyleResolver()->resolve($document, 400, 300, [], new Timeline(), 0, $containers);
        self::assertEqualsWithDelta(ScrollTimeline::view(150, 200, 200, 40, [116.0, 20.0], [['normal', 0.0], ['normal', 100.0]]), (float) $styles[$document->getElementById('subject')]['opacity'], .0001);

        $measurer = new \Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer();
        $metrics = static fn (float $scale) => new class($scale) implements \Symfony\Component\HtmlRenderer\Layout\LayoutMetricsInterface {
            public function __construct(private float $scale)
            {
            }

            public function resolveUnit(float $number, string $unit, bool $vertical, float $resolved): float
            {
                return $resolved;
            }

            public function constrainLength(float $length, string $property, bool $vertical, ?\Symfony\Component\HtmlRenderer\Font\FontStyle $font = null): float
            {
                return 'view-timeline-inset' === $property ? $length * $this->scale : $length;
            }

            public function outsideMarkerGutter(float $naturalGroupWidth): float
            {
                return 0;
            }

            public function scrollbarThickness(bool $vertical, bool $thin = false): float
            {
                return 0;
            }

            public function distribute(array $lengths, bool $vertical): array
            {
                return $lengths;
            }
        };
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><style>@keyframes fade{from{opacity:0}to{opacity:1}}#scroll{height:100px;overflow:auto}#subject{height:20px;animation:fade linear both;animation-timeline:view(block 10px)}.spacer{height:60px}</style><div id="scroll"><div class="spacer"></div><div id="subject">Subject</div><div class="spacer"></div></div>', new RenderingContext(200, 200, $measurer, null, $metrics(1)));
        $first = $session->render();
        $subject = $first->getDocument()->getElementById('subject');
        $before = $first->getComputedStyle($subject)['opacity'];
        $session->setContext(new RenderingContext(200, 200, $measurer, null, $metrics(2)));
        $after = $session->render()->getComputedStyle($subject)['opacity'];
        self::assertNotSame($before, $after);
        self::assertEqualsWithDelta(.3, (float) $before, .0001);
        self::assertEqualsWithDelta(.25, (float) $after, .0001);
    }

    public function testSharedViewRangeMathKeepsTheTerminalMinimumAnExplicitAdapterChoice()
    {
        self::assertEqualsWithDelta(9 / 59, ScrollTimeline::view(30, 40.5, 60.25, 20.5, [1.25, .75], [['cover', 0.0], ['cover', 100.0]]), .000001);
        self::assertSame(-.2, ScrollTimeline::view(0, .5, 1, 2, [0.0, 0.0], [['cover', 0.0], ['cover', 100.0]]));
        self::assertSame(0.0, ScrollTimeline::view(0, .5, 1, 2, [0.0, 0.0], [['cover', 0.0], ['cover', 100.0]], 1.0));
        self::assertSame(0.0, ScrollTimeline::view(0, 0, 0, 20, [0.0, 0.0], [['cover', 0.0], ['cover', 100.0]]));
        self::assertEqualsWithDelta(16 / 36, ScrollTimeline::view(0, 0, 0, 20, [0.0, 0.0], [['cover', 0.0], ['cover', 100.0]], 16), .000001);
    }
}
