<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Event\PointerEvent;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Layout\LayoutMetricsInterface;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class ScrollbarInteractionTest extends TestCase
{
    public function testScrollbarTracksAndCornerNeverActivateTheirAnchorOwner()
    {
        foreach ([[76, 8, 'y'], [4, 24, 'x'], [76, 24, 'corner']] as [$x, $y, $axis]) {
            $session = $this->document();
            $result = $session->render();
            self::assertSame($axis, $result->getScrollbarAt($x, $y)['axis']);
            $session->handlePointer(new PointerEvent('down', $x, $y));
            $session->handlePointer(new PointerEvent('up', $x, $y));
            self::assertNull($session->getTarget());
            $session->pointer($x, $y, true);
            self::assertNull($session->getTarget());
            $session->handlePointer(new PointerEvent('up', $x, $y));
            self::assertNull($session->getTarget());
        }
        $session = $this->document();
        $session->handlePointer(new PointerEvent('down', 4, 8));
        $session->handlePointer(new PointerEvent('up', 4, 8));
        self::assertSame('target', $session->getTarget()->id, 'The actual link content still activates.');
    }

    public function testTranslatedAndClippedChromeUsesTheSamePaintWitnessAsContent()
    {
        $session = $this->document('transform:translate(20px,10px)');
        self::assertSame('y', $session->render()->getScrollbarAt(96, 18)['axis']);
        $session->pointer(96, 18, true);
        self::assertNull($session->getTarget());
        $session = $this->document('', '<div style="width:60px;height:32px;overflow:hidden">', '</div>');
        self::assertNull($session->render()->getScrollbarAt(76, 8));
        $session->pointer(76, 8, true);
        self::assertNull($session->getTarget());
    }

    public function testAnOverlayOccludesScrollbarChromeAndReleaseWithoutPressDoesNotClick()
    {
        $session = $this->document('', '', '<button id="overlay" style="position:absolute;left:72px;top:0;width:8px;height:16px;padding:0;border:0;z-index:10">X</button>');
        $overlay = $session->getDocument()->getElementById('overlay');
        self::assertNull($session->render()->getScrollbarAt(76, 8));
        self::assertSame($overlay, $session->render()->hitTest(76, 8));
        $clicks = 0;
        $session->on('click', static function () use (&$clicks): void { ++$clicks; }, $overlay);
        $session->handlePointer(new PointerEvent('up', 76, 8));
        self::assertSame(0, $clicks);
        $session->handlePointer(new PointerEvent('down', 76, 8));
        $session->handlePointer(new PointerEvent('up', 76, 8));
        self::assertSame(1, $clicks);
        self::assertNull($session->getTarget());
    }

    private function document(string $extra = '', string $before = '', string $after = '')
    {
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
                return 0.0;
            }

            public function scrollbarThickness(bool $vertical, bool $thin = false): float
            {
                return $vertical ? 8.0 : 16.0;
            }

            public function distribute(array $lengths, bool $vertical): array
            {
                return $lengths;
            }
        };

        return new HtmlRenderer()->createSession($before.'<a href="#target" style="display:block;width:80px;height:32px;overflow:scroll;line-height:16px;'.$extra.'">one<br>two<br>three<br>four</a>'.$after.'<p id="target">Target</p>', new RenderingContext(300, 200, null, null, $metrics));
    }
}
