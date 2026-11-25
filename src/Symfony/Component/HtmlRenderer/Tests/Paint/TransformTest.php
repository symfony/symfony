<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Paint;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Geometry\Rect;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\Fragment;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\Paint\Transform;
use Symfony\Component\HtmlRenderer\RenderingResult;

final class TransformTest extends TestCase
{
    public function testTransformLeavesLayoutGeometryAndEmitsResolvedFractionalMatrix()
    {
        $box = new Box(null, 'block', ['width' => '10px', 'height' => '5px', 'background-color' => 'red', 'transform' => 'translate(2.25px,3.5px)']);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(0.0, $fragment->bounds->x);
        self::assertSame(10.0, $fragment->bounds->width);
        $operations = (new Painter())->paint($fragment)->getOperations();
        self::assertSame(['push-transform', 'rectangle', 'pop-transform'], array_column($operations, 'type'));
        self::assertSame([1.0, 0.0, 0.0, 1.0, 2.25, 3.5], $operations[0]['matrix']);
    }

    public function testTransformOriginAndPercentTranslationsUseReferenceBox()
    {
        $matrix = Transform::resolve(['transform' => 'translate(50%,25%) scale(2)', 'transform-origin' => '0 0'], new Rect(10, 20, 40, 20), static fn (string $token, bool $vertical): ?float => str_ends_with($token, '%') ? (float) $token * ($vertical ? 20 : 40) / 100 : (float) $token);
        self::assertSame([2.0, 0.0, 0.0, 2.0, 10.0, -15.0], $matrix);
    }

    public function testHitTestUsesInverseTransformAndAncestorClipSpace()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main><button>Click</button></main>');
        $parent = $document->querySelector('main');
        $button = $document->querySelector('button');
        $child = new Fragment($button, new Rect(0, 0, 10, 10), ['transform' => 'translate(5.5px)'], null, [], null, [1.0, 0.0, 0.0, 1.0, 5.5, 0.0]);
        $fragment = new Fragment($parent, new Rect(0, 0, 10, 10), ['overflow' => 'hidden'], null, [$child]);
        $result = new RenderingResult($document, new \SplObjectStorage(), $fragment, (new Painter())->paint($fragment));
        self::assertSame($button, $result->hitTest(6, 2));
        self::assertSame($parent, $result->hitTest(3, 2));
        self::assertNull($result->hitTest(12, 2));
    }

    public function testNestedTransformAndOpacityScopesComposeWithoutMovingLayout()
    {
        $child = new Fragment(null, new Rect(0, 0, 10, 10), ['transform' => 'translate(5.5px)', 'background-color' => 'red'], null, [], null, [1.0, 0.0, 0.0, 1.0, 5.5, 0.0]);
        $fragment = new Fragment(null, new Rect(0, 0, 20, 20), ['transform' => 'translate(10px)', 'opacity' => '.5'], null, [$child], null, [1.0, 0.0, 0.0, 1.0, 10.0, 0.0]);
        $operations = (new Painter())->paint($fragment)->getOperations();
        self::assertSame(['push-transform', 'push-opacity', 'push-transform', 'rectangle', 'pop-transform', 'pop-opacity', 'pop-transform'], array_column($operations, 'type'));
        self::assertSame([1.0, 0.0, 0.0, 1.0, 5.5, 0.0], $operations[2]['matrix']);
    }

    public function testNonInvertibleTransformCannotReceivePointerInput()
    {
        self::assertNull(Transform::inversePoint([0.0, 0.0, 0.0, 0.0, 0.0, 0.0], 0, 0));
        self::assertSame([2.0, 3.0], Transform::inversePoint([2.0, 0.0, 0.0, 2.0, 10.0, 20.0], 14, 26));
    }

    public function testTextRunsDoNotInheritTheirElementTransformAgain()
    {
        $box = new Box(null, 'inline-block', ['width' => '40px', 'height' => '20px', 'transform' => 'translateX(5.5px)'], [], 'Hello');
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(1, array_count_values(array_column((new Painter())->paint($fragment)->getOperations(), 'type'))['push-transform']);
    }

    public function testTransformResolutionDoesNotDependOnNumericLocale()
    {
        $previous = setlocale(\LC_NUMERIC, '0');
        try {
            if (false === setlocale(\LC_NUMERIC, 'fr_FR.UTF-8', 'fr_FR')) {
                self::markTestSkipped('The French locale is unavailable.');
            }
            $matrix = Transform::resolve(['transform' => 'translate(2.25px,3.5px)'], new Rect(0, 0, 10, 10), static fn (string $value): float => (float) $value);
            self::assertSame([1.0, 0.0, 0.0, 1.0, 2.25, 3.5], $matrix);
        } finally {
            setlocale(\LC_NUMERIC, $previous);
        }
    }

    public function testIndividualTranslatePrecedesTransformAndThreeDimensionalTranslationKeepsItsPlanarPart()
    {
        $length = static fn (string $value): float => (float) $value;
        $matrix = Transform::resolve(['translate' => '2.25px 3.5px', 'transform' => 'scale(2)', 'transform-origin' => '0 0'], new Rect(0, 0, 10, 10), $length);
        self::assertSame([2.0, 0.0, 0.0, 2.0, 2.25, 3.5], $matrix);
        $matrix = Transform::resolve(['transform' => 'translate3d(2.25px,3.5px,7px)'], new Rect(0, 0, 10, 10), $length);
        self::assertSame([1.0, 0.0, 0.0, 1.0, 2.25, 3.5], $matrix);
    }
}
