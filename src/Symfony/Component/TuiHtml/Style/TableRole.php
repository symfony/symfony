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
 * The part a container plays in a table, like CSS display: table, table-row...
 *
 * The columns of a table are sized across all its rows: each column is as
 * wide as its widest cell, so that the cells of a column line up.
 *
 * @experimental
 */
enum TableRole: string
{
    case Table = 'table';
    /** Rows grouped together, like <thead>, <tbody> and <tfoot> */
    case RowGroup = 'row-group';
    /** A row of cells, laid out horizontally */
    case Row = 'row';
    case Cell = 'cell';
    /** A column, like <col>: not drawn, its width is the least width of the columns it spans */
    case Column = 'column';
}
