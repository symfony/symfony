<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Render;

use Symfony\Component\Tui\Render\LineBufferInterface;

/** @internal */
final class FlowPiece
{
    /** The rows of the block before these, drawn over those of the blocks before it, see LayoutEngine::pulledPiece() */
    public int $over = 0;

    /**
     * The margins, top, right, bottom and left, of a float whose box paints all its cells, like a replaced element, an
     * image: nothing under its border box shows, its margins letting what is under them show; null for a float whose
     * cells that draw nothing let it show, see LayoutEngine::floatOver().
     *
     * @var array{int, int, int, int}|null
     */
    public ?array $opaqueBox = null;

    /** @param array{string, list<int>}|null $shape */
    public function __construct(public LineBufferInterface $lines, public readonly int $row, public readonly int $column, public readonly int $columns, public bool $flow, public readonly ?array $shape)
    {
    }
}
