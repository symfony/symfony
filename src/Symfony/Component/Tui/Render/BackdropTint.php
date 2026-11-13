<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Render;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\ColorScheme;

/**
 * Lays a translucent color over rendered lines, like the ::backdrop of a modal dialog over the page.
 *
 * Each cell has one foreground and one background color: both are blended
 * toward the tint, the default foreground taken as the color scheme says.
 * The terminal background is unknown: cells on it keep it, but for an
 * opaque tint, which paints them. Lines are filled to the width, so that
 * the empty cells are painted too.
 *
 * @internal
 */
final class BackdropTint
{
    private readonly ColorRewriter $rewriter;

    /**
     * @param int $percent The opacity of the tint, 0 to 100
     */
    public function __construct(Color $tint, int $percent, ColorScheme $scheme)
    {
        $rgb = $tint->toRgb();
        $tint = [$rgb['r'], $rgb['g'], $rgb['b']];
        $this->rewriter = new ColorRewriter(
            static fn (array $color): array => array_map(static fn (int $value, int $to) => (int) round($value + ($to - $value) * $percent / 100), $color, $tint),
            $scheme,
            // An opaque tint paints the cells on the terminal background too
            100 === $percent ? $tint : null,
        );
    }

    /**
     * @param list<string> $lines
     *
     * @return list<string>
     */
    public function apply(array $lines, int $columns, int $rows): array
    {
        while (\count($lines) < $rows) {
            $lines[] = '';
        }

        return array_map(function (string $line) use ($columns): string {
            // Filled to the width, so that the empty cells are painted too
            $width = AnsiUtils::visibleWidth($line);

            return $this->rewriter->line($line.($width < $columns ? str_repeat(' ', $columns - $width) : ''));
        }, $lines);
    }
}
