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
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\Fragment;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;
use Symfony\Component\HtmlRenderer\Paint\Painter;

final class ReplacedImageRenderingTest extends TestCase
{
    public function testAllObjectFitsAllocateActualDrawDimensionsAndClipTheContent()
    {
        foreach (['fill' => [20.0, 20.0, 0.0, 0.0], 'contain' => [20.0, 10.0, 0.0, 5.0], 'cover' => [40.0, 20.0, -10.0, 0.0], 'none' => [64.0, 32.0, -22.0, -6.0], 'scale-down' => [20.0, 10.0, 0.0, 5.0]] as $fit => [$width, $height, $x, $y]) {
            $provider = $this->provider();
            $fragment = $this->layout($provider, ['width' => '20px', 'height' => '20px', 'object-fit' => $fit]);
            $leaf = $this->content($fragment);
            self::assertSame([20.0, 20.0], [$fragment->bounds->width, $fragment->bounds->height]);
            self::assertSame([[$width, $height]], $provider->allocations);
            self::assertSame([$x, $y, $width, $height], [$leaf->bounds->x, $leaf->bounds->y, $leaf->bounds->width, $leaf->bounds->height]);
            if ('fill' !== $fit) {
                self::assertSame('clip', $fragment->children[0]->style['overflow']);
            }
        }
    }

    public function testHeightTransferFinalConstraintsAndFractionalObjectPosition()
    {
        $provider = $this->provider();
        $fragment = $this->layout($provider, ['height' => '7.5px', 'box-sizing' => 'border-box', 'padding' => '1.25px', 'border-width' => '.5px']);
        self::assertSame([11.5, 7.5], [$fragment->bounds->width, $fragment->bounds->height]);
        self::assertSame([[8.0, 4.0]], $provider->allocations);
        $provider = $this->provider();
        $fragment = $this->layout($provider, ['width' => '20px', 'max-height' => '4px', 'object-fit' => 'contain']);
        self::assertSame(4.0, $fragment->bounds->height);
        self::assertSame([[8.0, 4.0]], $provider->allocations);
        self::assertSame(6.0, $this->content($fragment)->bounds->x);
        $provider = $this->provider();
        $fragment = $this->layout($provider, ['width' => '20px', 'min-height' => '14px', 'object-fit' => 'contain']);
        self::assertSame(14.0, $fragment->bounds->height);
        self::assertSame(2.0, $this->content($fragment)->bounds->y);
        $provider = $this->provider();
        $fragment = $this->layout($provider, ['width' => '20px', 'height' => '20px', 'object-fit' => 'contain', 'object-position' => 'right 1.25px bottom .5px']);
        self::assertSame([-1.25, 9.5], [$this->content($fragment)->bounds->x, $this->content($fragment)->bounds->y]);
        self::assertSame('push-clip', new Painter()->paint($fragment)->getOperations()[0]['type']);
    }

    public function testRepeatedResizingDoesNotUseAnEarlierAllocationAsIntrinsicSize()
    {
        $provider = $this->provider();
        $this->layout($provider, ['width' => '20px']);
        $this->layout($provider, ['width' => '40px']);
        $this->layout($provider, ['height' => '7.5px']);
        self::assertSame([[20.0, 10.0], [40.0, 20.0], [15.0, 7.5]], $provider->allocations);
    }

    public function testNaturalPreferredHeightIsPreservedUntilCssResizesTheImage()
    {
        $provider = $this->provider(1.0);
        $natural = $this->layout($provider, []);
        self::assertSame([64.0, 32.0], [$natural->bounds->width, $natural->bounds->height]);
        $resized = $this->layout($provider, ['width' => '20px']);
        self::assertSame([20.0, 20.0], [$resized->bounds->width, $resized->bounds->height]);
        $explicit = $this->layout($provider, ['width' => '64px']);
        self::assertSame(64.0, $explicit->bounds->height, 'An explicit width uses the source ratio even when its value equals the natural preferred width.');
        $fractional = $this->layout($provider, ['padding' => '.1px', 'border-width' => '.2px']);
        self::assertSame(32.6, $fractional->bounds->height);
        self::assertSame(32.0, $this->content($fractional)->bounds->height);
        $changed = $this->layout($provider, ['min-width' => '64.0000000001px']);
        self::assertSame(64.0000000001, $changed->bounds->height, 'A small real size change must not be treated as roundoff.');
        $changed = $this->layout($provider, ['max-width' => '63.9999999999px']);
        self::assertSame(63.9999999999, $changed->bounds->height);
        $box = new Box(null, 'flex', ['width' => '80px', 'align-items' => 'start'], [new Box(null, 'inline-block', [], [], null, null, null, null, null, null, null, $provider)]);
        $forced = new LayoutEngine(new MonospaceTextMeasurer())->layout($box, 200, 100);
        self::assertSame(32.0, $forced->children[0]->bounds->height);
    }

    public function testExplicitlySizedOrdinaryContentKeepsItsAllocationWithoutAnImageRatio()
    {
        $provider = new class implements ReplacedContentInterface {
            public int $queries = 0;
            public array $allocation = [];

            public function getIntrinsicSize(): IntrinsicSize
            {
                ++$this->queries;

                return new IntrinsicSize(0, 30, 10);
            }

            public function layout(float $width, float $availableHeight): ContentLayout
            {
                $this->allocation = [$width, $availableHeight];

                return new ContentLayout(10, new DisplayList());
            }
        };
        $fragment = $this->layout($provider, ['width' => '20px', 'height' => '10px', 'object-fit' => 'none']);
        self::assertSame([20.0, 10.0], [$fragment->bounds->width, $fragment->bounds->height]);
        self::assertSame([20.0, 10.0], $provider->allocation);
        self::assertLessThanOrEqual(1, $provider->queries, 'Fixed geometry must not repeatedly measure ordinary content to inspect optional metadata.');
        self::assertArrayNotHasKey('-renderer-replaced', $fragment->style);
    }

    private function layout(ReplacedContentInterface $provider, array $style): Fragment
    {
        $box = new Box(null, 'inline-block', $style, [], null, null, null, null, null, null, null, $provider);

        return new LayoutEngine(new MonospaceTextMeasurer())->layout($box, 200, 100);
    }

    private function content(Fragment $fragment): Fragment
    {
        if (null !== $fragment->content) {
            return $fragment;
        }

        return $this->content($fragment->children[0]);
    }

    private function provider(float $ratio = 2.0): ReplacedContentInterface
    {
        return new class($ratio) implements ReplacedContentInterface {
            public array $allocations = [];

            public function __construct(private readonly float $ratio)
            {
            }

            public function getIntrinsicSize(): IntrinsicSize
            {
                return new IntrinsicSize(0, 64, 32, $this->ratio);
            }

            public function layout(float $width, float $availableHeight): ContentLayout
            {
                $this->allocations[] = [$width, $availableHeight];

                return new ContentLayout($availableHeight, new DisplayList([['type' => 'rectangle', 'x' => 0.0, 'y' => 0.0, 'width' => $width, 'height' => $availableHeight, 'color' => 'red']]));
            }
        };
    }
}
