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

use Symfony\Component\HtmlRenderer\Geometry\Rect;

/** @internal */
final readonly class ScrollGeometry
{
    public float $maxX;
    public float $maxY;
    public float $offsetX;
    public float $offsetY;

    /** @param array<string, list<\Dom\Element>> $snapped Actual targets at the final snapped offset on each physical axis */
    public function __construct(public Rect $port, public Rect $content, float $offsetX = 0.0, float $offsetY = 0.0, public bool $horizontal = true, public bool $vertical = true, public array $reserved = [0.0, 0.0, 0.0, 0.0], public bool $horizontalBar = false, public bool $verticalBar = false, public bool $cycleResolved = false, public array $snapped = [])
    {
        if (4 !== \count($reserved) || array_any($reserved, static fn (float $value): bool => !is_finite($value) || $value < 0)) {
            throw new \InvalidArgumentException('Scrollbar insets must contain four finite non-negative lengths.');
        }
        if (!is_finite($offsetX) || !is_finite($offsetY)) {
            throw new \InvalidArgumentException('Scroll offsets must be finite.');
        }
        $this->maxX = $horizontal ? max(0.0, $content->x + $content->width - $port->x - $port->width) : 0.0;
        $this->maxY = $vertical ? max(0.0, $content->y + $content->height - $port->y - $port->height) : 0.0;
        $this->offsetX = max(0.0, min($this->maxX, $offsetX));
        $this->offsetY = max(0.0, min($this->maxY, $offsetY));
    }

    public function moved(float $x, float $y): self
    {
        return new self(new Rect($this->port->x + $x, $this->port->y + $y, $this->port->width, $this->port->height), new Rect($this->content->x + $x, $this->content->y + $y, $this->content->width, $this->content->height), $this->offsetX, $this->offsetY, $this->horizontal, $this->vertical, $this->reserved, $this->horizontalBar, $this->verticalBar, $this->cycleResolved, $this->snapped);
    }
}
