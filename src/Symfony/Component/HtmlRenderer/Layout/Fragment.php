<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Layout;

use Symfony\Component\HtmlRenderer\Content\ContentLayout;
use Symfony\Component\HtmlRenderer\Geometry\Rect;
use Symfony\Component\HtmlRenderer\Paint\BorderImageSource;
use Symfony\Component\HtmlRenderer\Resource\ImageResource;
use Symfony\Component\HtmlRenderer\Text\TextRun;

/**
 * @internal
 */
final readonly class Fragment
{
    /**
     * @param array<string, string> $style
     * @param list<self>            $children
     */
    public function __construct(public ?\Dom\Node $node, public Rect $bounds, public array $style = [], public ?string $text = null, public array $children = [], public ?ImageResource $image = null, public ?array $transform = null, public array $backgrounds = [], public ?TextRun $run = null, public ?string $pseudoElement = null, public ?int $topLayer = null, public ?ScrollGeometry $scroll = null, public ?BorderImageSource $borderImage = null, public ?\Dom\Element $anchor = null, public ?ContentLayout $content = null)
    {
    }

    public function getPaddingBox(): Rect
    {
        $insets = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $insets[] = (float) ($this->style['border-'.$side.'-width'] ?? 0);
        }

        return new Rect($this->bounds->x + $insets[3], $this->bounds->y + $insets[0], max(0.0, $this->bounds->width - $insets[1] - $insets[3]), max(0.0, $this->bounds->height - $insets[0] - $insets[2]));
    }

    public function getContentBox(): Rect
    {
        $padding = $this->scroll->port ?? $this->getPaddingBox();
        $insets = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $insets[] = (float) ($this->style['padding-'.$side] ?? 0);
        }

        return new Rect($padding->x + $insets[3], $padding->y + $insets[0], max(0.0, $padding->width - $insets[1] - $insets[3]), max(0.0, $padding->height - $insets[0] - $insets[2]));
    }
}
