<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Content;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Content\ContentLayout;
use Symfony\Component\HtmlRenderer\Content\IntrinsicSize;
use Symfony\Component\HtmlRenderer\Content\ReplacedContentInterface;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Layout\LayoutMetricsInterface;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class ReplacedRenderingTest extends TestCase
{
    public function testNaturalDimensionsKeepFractionalOuterCssAndLocalPainting()
    {
        $provider = $this->provider();
        $seen = [];
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>custom-view{display:block;color:red;margin:2.5px;padding:1.25px;border:.5px solid blue;background:yellow}</style><custom-view id="custom"></custom-view>');
        $resolver = static function (\Dom\Element $element, array $style) use ($provider, &$seen): ?ReplacedContentInterface {
            if ('custom-view' !== $element->localName) {
                return null;
            }
            $seen[] = [$element, $style['color']];

            return $provider;
        };
        $result = (new HtmlRenderer())->render($document, $this->context($resolver));
        $element = $document->getElementById('custom');
        self::assertSame([[$element, 'red']], $seen);
        $bounds = $result->getBounds($element)[0];
        self::assertSame(29.0, $bounds->width);
        self::assertSame(10.75, $bounds->height);
        self::assertSame(25.5, $provider->calls[0][0]);
        $operations = $result->getDisplayList()->getOperations();
        $local = array_values(array_filter($operations, static fn (array $op): bool => 'text' === $op['type'] && 'CUSTOM' === $op['text']));
        self::assertCount(1, $local);
        self::assertSame(0.0, $local[0]['x']);
        $translations = array_values(array_filter($operations, static fn (array $op): bool => 'push-transform' === $op['type']));
        self::assertSame([1.0, 0.0, 0.0, 1.0, $bounds->x + 1.75, $bounds->y + 1.75], $translations[0]['matrix']);
    }

    public function testReplacementIsAtomicAndUsesResolvedHeightConstraints()
    {
        $provider = $this->provider();
        $calls = [];
        $resolver = static function (\Dom\Element $element) use ($provider, &$calls): ?ReplacedContentInterface {
            $calls[] = $element->localName;

            return 'custom-view' === $element->localName ? $provider : null;
        };
        $result = (new HtmlRenderer())->render('<style>custom-view{display:block;width:30.5px;height:14.25px;padding:1.25px;border:.5px solid;overflow:hidden}custom-view::before{content:"BEFORE"}custom-view::after{content:"AFTER"}</style><custom-view id="custom"><span id="child">CHILD</span></custom-view>', $this->context($resolver));
        self::assertNotContains('span', $calls);
        self::assertSame([30.5, 14.25], $provider->calls[0]);
        self::assertSame([], $result->getBounds($result->getDocument()->getElementById('child')));
        self::assertSame(17.75, $result->getBounds($result->getDocument()->getElementById('custom'))[0]->height);
        $text = implode('', array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']), 'text'));
        self::assertSame('CUSTOM', $text);
    }

    public function testFlexAllocationUsesProviderMinimumAndPreferredWidths()
    {
        $provider = $this->provider(10, 40);
        $resolver = static fn (\Dom\Element $element): ?ReplacedContentInterface => 'custom-view' === $element->localName ? $provider : null;
        $result = (new HtmlRenderer())->render('<div style="display:flex;width:50px"><custom-view id="a"></custom-view><custom-view id="b"></custom-view></div>', $this->context($resolver));
        self::assertSame(25.0, $result->getBounds($result->getDocument()->getElementById('a'))[0]->width);
        self::assertSame(25.0, $result->getBounds($result->getDocument()->getElementById('b'))[0]->width);
    }

    public function testShadowProviderReceivesStableOriginalSourceElements()
    {
        $provider = $this->provider();
        $seen = [];
        $resolver = static function (\Dom\Element $element) use ($provider, &$seen): ?ReplacedContentInterface {
            if ('custom-view' !== $element->localName) {
                return null;
            }
            $seen[] = $element;

            return $provider;
        };
        $session = (new HtmlRenderer())->createSession('<user-card><template shadowrootmode="open"><custom-view></custom-view></template></user-card>', $this->context($resolver));
        $first = $session->render();
        self::assertCount(1, $seen);
        self::assertFalse($seen[0]->isConnected);
        self::assertSame($session->getDocument(), $seen[0]->ownerDocument);
        self::assertNotEmpty($first->getBounds($seen[0]));
        $session->getDocument()->body->appendChild($session->getDocument()->createElement('aside'));
        $session->render();
        self::assertSame($seen[0], $seen[1]);
    }

    public function testEmbeddedSessionsPreserveTheResolver()
    {
        $provider = $this->provider();
        $resolver = static fn (\Dom\Element $element): ?ReplacedContentInterface => 'custom-view' === $element->localName ? $provider : null;
        $result = (new HtmlRenderer())->render('<iframe srcdoc="&lt;custom-view&gt;CHILD&lt;/custom-view&gt;"></iframe>', $this->context($resolver));
        $text = implode('', array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']), 'text'));
        self::assertSame('CUSTOM', $text);
    }

    public function testContentLeavesUseOrdinaryOverflowAndScrolling()
    {
        foreach (['visible', 'hidden', 'auto'] as $overflow) {
            $provider = $this->provider(10, 30.5, 20);
            $resolver = static fn (\Dom\Element $element): ?ReplacedContentInterface => 'custom-view' === $element->localName ? $provider : null;
            $metrics = new class implements LayoutMetricsInterface {
                public function resolveUnit(float $number, string $unit, bool $vertical, float $resolved): float
                {
                    return $resolved;
                }

                public function constrainLength(float $length, string $property, bool $vertical, ?FontStyle $font = null): float
                {
                    return $length;
                }

                public function outsideMarkerGutter(float $naturalGroupWidth): float
                {
                    return 0;
                }

                public function scrollbarThickness(bool $vertical, bool $thin = false): float
                {
                    return 4;
                }

                public function distribute(array $lengths, bool $vertical): array
                {
                    return $lengths;
                }
            };
            $context = new RenderingContext(200, 100, null, null, $metrics, null, [], '', [], null, $resolver);
            $session = (new HtmlRenderer())->createSession('<custom-view id="custom" style="display:block;width:30.5px;height:10px;overflow:'.$overflow.'"></custom-view>', $context);
            $result = $session->render();
            $element = $session->getDocument()->getElementById('custom');
            $bounds = $result->getBounds($element);
            self::assertCount(2, $bounds);
            self::assertSame(10.0, $bounds[0]->height);
            self::assertSame(20.0, $bounds[1]->height);
            if ('visible' === $overflow) {
                self::assertNull($result->getScrollGeometry($element));
                self::assertSame($element, $result->hitTest($bounds[0]->x + 1, $bounds[0]->y + 15));
            } else {
                $geometry = $result->getScrollGeometry($element);
                self::assertSame(10.0, $geometry->maxY);
                self::assertSame('auto' === $overflow, $geometry->verticalBar);
                self::assertNotSame($element, $result->hitTest($bounds[0]->x + 1, $bounds[0]->y + 15));
                $session->scroll($element, 0, 10);
                $moved = $session->render()->getBounds($element);
                self::assertSame($bounds[1]->y - 10, $moved[1]->y);
                self::assertSame(20.0, $moved[1]->height);
            }
        }
    }

    public function testDisplayNoneSuppressesProviderCallsAndFallbackPainting()
    {
        $calls = [];
        $provider = $this->provider();
        $resolver = static function (\Dom\Element $element) use (&$calls, $provider): ?ReplacedContentInterface {
            $calls[] = $element->localName;

            return 'custom-view' === $element->localName ? $provider : null;
        };
        $result = (new HtmlRenderer())->render('<custom-view id="hidden" style="display:none">HIDDEN</custom-view>', $this->context($resolver));
        self::assertNotContains('custom-view', $calls);
        self::assertSame([], $result->getBounds($result->getDocument()->getElementById('hidden')));
        self::assertSame([], array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']));
    }

    public function testPositionedAndGridCopiesKeepOnlyPureContentLayouts()
    {
        $provider = $this->provider();
        $resolver = static fn (\Dom\Element $element): ?ReplacedContentInterface => 'custom-view' === $element->localName ? $provider : null;
        $result = (new HtmlRenderer())->render('<div style="position:relative;width:100px;height:50px"><custom-view id="positioned" style="position:absolute;left:7.25px;top:9.5px;width:30.5px"></custom-view></div><div style="display:grid;width:50px"><custom-view id="grid"></custom-view></div>', $this->context($resolver));
        $positioned = $result->getBounds($result->getDocument()->getElementById('positioned'));
        self::assertCount(2, $positioned);
        self::assertSame(7.25, $positioned[1]->x);
        self::assertSame(9.5, $positioned[1]->y);
        self::assertSame(50.0, $result->getBounds($result->getDocument()->getElementById('grid'))[1]->width);
        $layouts = [];
        $visit = static function ($fragment) use (&$visit, &$layouts): void {
            self::assertFalse(property_exists($fragment, 'replacedContent'));
            if (null !== $fragment->content) {
                $layouts[] = $fragment->content;
            }
            foreach ($fragment->children as $child) {
                $visit($child);
            }
        };
        $visit($result->getLayout());
        self::assertCount(2, $layouts);
        self::assertInstanceOf(ContentLayout::class, $layouts[0]);
    }

    public function testExplicitAutoKeepsNaturalContentSizeWithBorderBoxSizing()
    {
        $provider = $this->provider();
        $resolver = static fn (\Dom\Element $element): ?ReplacedContentInterface => 'custom-view' === $element->localName ? $provider : null;
        $result = (new HtmlRenderer())->render('<custom-view id="custom" style="display:block;width:auto;box-sizing:border-box;padding:1.25px;border:.5px solid"></custom-view>', $this->context($resolver));
        $bounds = $result->getBounds($result->getDocument()->getElementById('custom'));
        self::assertSame(29.0, $bounds[0]->width);
        self::assertSame(25.5, $bounds[1]->width);
    }

    public function testPositionedAutoUsesNaturalWidthBetweenOppositeInsets()
    {
        $provider = $this->provider();
        $resolver = static fn (\Dom\Element $element): ?ReplacedContentInterface => 'custom-view' === $element->localName ? $provider : null;
        $result = (new HtmlRenderer())->render('<div style="position:relative;width:100px;height:50px"><custom-view id="custom" style="position:absolute;left:5px;right:5px;width:auto;margin:auto"></custom-view></div>', $this->context($resolver));
        $bounds = $result->getBounds($result->getDocument()->getElementById('custom'))[0];
        self::assertSame(25.5, $bounds->width);
        self::assertSame(37.25, $bounds->x);
    }

    private function context(\Closure $resolver): RenderingContext
    {
        return new RenderingContext(200, 100, null, null, null, null, [], '', [], null, $resolver);
    }

    private function provider(float $minimum = 12.5, float $preferred = 25.5, float $height = 7.25): ReplacedContentInterface
    {
        return new class($minimum, $preferred, $height) implements ReplacedContentInterface {
            public array $calls = [];

            public function __construct(private float $minimum, private float $preferred, private float $height)
            {
            }

            public function getIntrinsicSize(): IntrinsicSize
            {
                return new IntrinsicSize($this->minimum, $this->preferred, $this->height);
            }

            public function layout(float $width, float $availableHeight): ContentLayout
            {
                $this->calls[] = [$width, $availableHeight];

                return new ContentLayout($this->height, new DisplayList([['type' => 'text', 'x' => 0.0, 'y' => 0.0, 'width' => $width, 'height' => $this->height, 'text' => 'CUSTOM', 'color' => 'red']]));
            }
        };
    }
}
