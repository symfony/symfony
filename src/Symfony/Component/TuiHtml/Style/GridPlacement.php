<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Style;

/**
 * Where an item goes on a grid, like CSS grid-column, grid-row and grid-area.
 *
 * Lines count from 1, like CSS; a negative line counts from the end, -1
 * being the last line. A line can be named instead, resolved by the grid
 * (see GridTemplate::line()). An item without a start is placed automatically.
 *
 * @experimental
 */
final class GridPlacement
{
    public function __construct(public readonly ?string $area = null, public readonly ?int $columnStart = null, public readonly ?int $columnEnd = null, public readonly int $columnSpan = 1, public readonly ?int $rowStart = null, public readonly ?int $rowEnd = null, public readonly int $rowSpan = 1, public readonly ?string $columnStartName = null, public readonly ?string $columnEndName = null, public readonly ?string $rowStartName = null, public readonly ?string $rowEndName = null)
    {
    }

    public function withColumns(?int $start, ?int $end, int $span, ?string $startName = null, ?string $endName = null): self
    {
        return new self(null, $start, $end, max(1, min(1000, $span)), $this->rowStart, $this->rowEnd, $this->rowSpan, $startName, $endName, $this->rowStartName, $this->rowEndName);
    }

    public function withRows(?int $start, ?int $end, int $span, ?string $startName = null, ?string $endName = null): self
    {
        return new self(null, $this->columnStart, $this->columnEnd, $this->columnSpan, $start, $end, max(1, min(1000, $span)), $this->columnStartName, $this->columnEndName, $startName, $endName);
    }
}
