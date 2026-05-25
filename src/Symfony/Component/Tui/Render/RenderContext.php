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

use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Widget\Figlet\FontRegistry;

/**
 * Context passed to widgets during rendering.
 *
 * Contains the available dimensions for rendering in terminal character cells,
 * the resolved style for the widget, and the font registry for FIGlet rendering.
 *
 * This is an immutable value object - use with*() methods to create modified copies.
 *
 * ## Terminology: columns and rows
 *
 * This class uses `columns` and `rows` to match terminal conventions:
 * - Terminals measure size in character cells (columns × rows), not pixels
 * - The TerminalInterface uses `getColumns()` and `getRows()`
 * - Standard tools like `stty`, `tput`, and env vars `COLUMNS`/`LINES` use this terminology
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class RenderContext
{
    private readonly Style $style;
    private readonly FontRegistry $fontRegistry;

    public function __construct(
        private readonly int $columns,
        private readonly int $rows,
        ?Style $style = null,
        ?FontRegistry $fontRegistry = null,
    ) {
        $this->style = $style ?? new Style();
        $this->fontRegistry = $fontRegistry ?? new FontRegistry();
    }

    public function getColumns(): int
    {
        return $this->columns;
    }

    public function getRows(): int
    {
        return $this->rows;
    }

    public function getStyle(): Style
    {
        return $this->style;
    }

    public function getFontRegistry(): FontRegistry
    {
        return $this->fontRegistry;
    }

    /**
     * Create a new context with a different column count.
     */
    public function withColumns(int $columns): self
    {
        return new self($columns, $this->rows, $this->style, $this->fontRegistry);
    }

    /**
     * Create a new context with a different row count.
     */
    public function withRows(int $rows): self
    {
        return new self($this->columns, $rows, $this->style, $this->fontRegistry);
    }

    /**
     * Create a new context with different dimensions.
     */
    public function withSize(int $columns, int $rows): self
    {
        return new self($columns, $rows, $this->style, $this->fontRegistry);
    }

    /**
     * Create a new context with a resolved style.
     */
    public function withStyle(Style $style): self
    {
        return new self($this->columns, $this->rows, $style, $this->fontRegistry);
    }
}
