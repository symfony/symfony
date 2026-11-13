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
    /** The style of a context given none: styles do not change, one does for all */
    private static ?Style $plain = null;

    private readonly Style $style;
    private readonly FontRegistry $fontRegistry;

    public function __construct(
        private readonly int $columns,
        private readonly int $rows,
        ?Style $style = null,
        ?FontRegistry $fontRegistry = null,
        private readonly bool $fillRows = false,
        private readonly ?int $containingColumns = null,
    ) {
        $this->style = $style ?? self::$plain ??= new Style();
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

    /**
     * The width of the containing block, which percentage padding and margins are a share of: the columns given, unless
     * a row gave the widget its share of its own.
     */
    public function getContainingColumns(): int
    {
        return $this->containingColumns ?? $this->columns;
    }

    /**
     * Create a new context in a containing block of another width than the columns given.
     */
    public function withContainingColumns(?int $columns): self
    {
        return new self($this->columns, $this->rows, $this->style, $this->fontRegistry, $this->fillRows, $columns);
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
     * Returns true when this widget should fill its allocated rows (root render or vertically-expanded).
     *
     * Used to gate VerticalAlign padding on vertical containers: non-expanded nested containers
     * receive the parent's row budget as a maximum, not a target to fill.
     */
    public function shouldFillRows(): bool
    {
        return $this->fillRows;
    }

    /**
     * Create a new context with a different column count.
     */
    public function withColumns(int $columns): self
    {
        return new self($columns, $this->rows, $this->style, $this->fontRegistry, $this->fillRows);
    }

    /**
     * Create a new context with a different row count.
     */
    public function withRows(int $rows): self
    {
        return new self($this->columns, $rows, $this->style, $this->fontRegistry, false, $this->containingColumns);
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
        return new self($this->columns, $this->rows, $style, $this->fontRegistry, $this->fillRows, $this->containingColumns);
    }
}
