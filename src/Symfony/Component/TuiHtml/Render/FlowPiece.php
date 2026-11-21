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
    /** @param array{string, list<int>}|null $shape */
    public function __construct(public LineBufferInterface $lines, public readonly int $row, public readonly int $column, public readonly int $columns, public bool $flow, public readonly ?array $shape)
    {
    }
}
