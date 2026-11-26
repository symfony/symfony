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
use Symfony\Component\HtmlRenderer\Geometry\Rect;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Layout\Fragment;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\RenderingContext;

class ContentSurfaceTest extends TestCase
{
    public function testSurfaceCoversUsedPaddingButNotBorderBeforeLocalContent()
    {
        $layout = new ContentLayout(10.0, new DisplayList([['type' => 'text', 'text' => 'X', 'x' => 0.0, 'y' => 0.0]]), true, '#123456');
        $fragment = $this->fragment($layout);
        $operations = new Painter()->paint($fragment)->getOperations();
        $surfaces = array_values(array_filter($operations, static fn (array $op): bool => 'rectangle' === $op['type']));
        self::assertSame([['type' => 'rectangle', 'x' => 11.0, 'y' => 21.0, 'width' => 28.0, 'height' => 18.0, 'color' => '#123456']], $surfaces);
        self::assertLessThan(array_search('text', array_column($operations, 'type')), array_search($surfaces[0], $operations));
        self::assertSame(13.0, $fragment->getContentBox()->x);
    }

    public function testLayoutOwnedSurfaceUsesPrincipalPaddingInsteadOfTheAnonymousDrawingBox()
    {
        $provider = new class implements ReplacedContentInterface {
            public function getIntrinsicSize(): IntrinsicSize
            {
                return new IntrinsicSize(20, 20, 10);
            }

            public function layout(float $width, float $availableHeight): ContentLayout
            {
                return new ContentLayout(10, new DisplayList(), true, '#123456');
            }
        };
        $result = new HtmlRenderer()->render('<style>custom-view{display:block;width:20px;padding:2px;border:1px solid red}</style><custom-view></custom-view>', new RenderingContext(100, 100, null, null, null, null, [], '', [], null, static fn (\Dom\Element $element) => 'custom-view' === $element->localName ? $provider : null));
        $surfaces = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'rectangle' === $op['type']));
        self::assertSame(24.0, $surfaces[0]['width']);
        self::assertSame(14.0, $surfaces[0]['height']);
    }

    public function testDefaultContentRemainsTransparentAndSurfaceSharesOuterScopes()
    {
        self::assertNotContains('rectangle', array_column(new Painter()->paint($this->fragment(new ContentLayout(10, new DisplayList())))->getOperations(), 'type'));
        $layout = new ContentLayout(10, new DisplayList(), true, [[1, 2, 3], 0.5]);
        $fragment = $this->fragment($layout, ['overflow' => 'hidden', 'opacity' => '.5'], [1.0, 0.0, 0.0, 1.0, 3.5, 2.25]);
        $operations = new Painter()->paint($fragment)->getOperations();
        $types = array_column($operations, 'type');
        $surface = array_search('rectangle', $types);
        self::assertLessThan($surface, array_search('push-transform', $types));
        self::assertLessThan($surface, array_search('push-opacity', $types));
        self::assertLessThan($surface, array_search('push-clip', $types));
        self::assertGreaterThan($surface, array_search('pop-opacity', $types));
        self::assertGreaterThan($surface, array_search('pop-transform', $types));
        self::assertSame([[1, 2, 3], 0.5], $operations[$surface]['color']);
    }

    public function testOverflowingReplacedContentPaintsItsAlphaSurfaceOnceWithinTheHostScopes()
    {
        $provider = new class implements ReplacedContentInterface {
            public function getIntrinsicSize(): IntrinsicSize
            {
                return new IntrinsicSize(20, 20, 30);
            }

            public function layout(float $width, float $availableHeight): ContentLayout
            {
                return new ContentLayout(30, new DisplayList([['type' => 'text', 'text' => 'X', 'x' => 0.0, 'y' => 0.0]]), false, [[1, 2, 3], 0.5]);
            }
        };
        $result = new HtmlRenderer()->render('<style>custom-view{display:block;width:20px;height:10px;padding:2px;border:1px solid red;overflow:hidden;opacity:.5;transform:translate(3.5px,2.25px)}</style><custom-view></custom-view>', new RenderingContext(100, 100, null, null, null, null, [], '', [], null, static fn (\Dom\Element $element) => 'custom-view' === $element->localName ? $provider : null));
        $operations = $result->getDisplayList()->getOperations();
        $surfaces = array_values(array_filter($operations, static fn (array $op): bool => 'rectangle' === $op['type']));
        self::assertCount(1, $surfaces);
        self::assertSame([[1, 2, 3], 0.5], $surfaces[0]['color']);
        self::assertSame(24.0, $surfaces[0]['width']);
        self::assertSame(14.0, $surfaces[0]['height']);
        $types = array_column($operations, 'type');
        self::assertCount(1, array_filter($types, static fn (string $type): bool => 'push-clip' === $type));
        self::assertLessThan(array_search('rectangle', $types), array_search('push-opacity', $types));
        self::assertLessThan(array_search('rectangle', $types), array_search('push-transform', $types));
    }

    public function testExplicitPrincipalSurfaceTakesPrecedenceWithoutDoubleCompositing()
    {
        $fragment = new Fragment(null, new Rect(0, 0, 20, 10), [], null, [], null, null, [], null, null, null, null, null, null, new ContentLayout(10, new DisplayList(), true, [[1, 2, 3], 0.5]), [[4, 5, 6], 0.5]);
        $surfaces = array_values(array_filter(new Painter()->paint($fragment)->getOperations(), static fn (array $op): bool => 'rectangle' === $op['type']));
        self::assertCount(1, $surfaces);
        self::assertSame([[4, 5, 6], 0.5], $surfaces[0]['color']);
    }

    public function testSurfaceRequiresTheSameFinitePortableDataAsLocalOperations()
    {
        $this->expectException(\InvalidArgumentException::class);
        new ContentLayout(10, new DisplayList(), true, [[1, 2, \INF], 1.0]);
    }

    private function fragment(ContentLayout $content, array $style = [], ?array $transform = null): Fragment
    {
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $style['border-'.$side.'-width'] = '1';
            $style['padding-'.$side] = '2';
        }

        return new Fragment(null, new Rect(10, 20, 30, 20), $style, null, [], null, $transform, [], null, null, null, null, null, null, $content);
    }
}
