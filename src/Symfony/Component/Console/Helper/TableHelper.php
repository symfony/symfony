<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Provides helpers to display table output.
 *
 * @author Саша Стаменковић <umpirsky@gmail.com>
 */
class TableHelper extends Helper
{
    /**
     * Table headers.
     *
     * @var array
     */
    private $headers = array();

    /**
     * Table rows.
     *
     * @var array
     */
    private $rows = array();

    // Rendering options
    private $backgroundChar  = ' ';
    private $horizontalBorderChar = '-';
    private $verticalBorderChar = '|';
    private $edgeChar = '+';
    private $cellHeaderFormat = '<info>%s</info>';
    private $cellRowFormat = '<comment>%s</comment>';
    private $borderFormat = '%s';
    private $padType = STR_PAD_RIGHT;

    /**
     * Column widths cache.
     *
     * @var array
     */
    private $columnWidths = array();

    /**
     * Number of columns cache.
     *
     * @var array
     */
    private $numberOfColumns;

    /**
     * @var OutputInterface
     */
    private $output;

    public function setHeaders(array $headers)
    {
        $this->headers = array_values($headers);

        return $this;
    }

    public function setRows(array $rows)
    {
        $this->rows = array();

        foreach ($rows as $row) {
            $this->addRow($row);
        }

        return $this;
    }

    public function addRows(array $rows)
    {
        foreach ($rows as $row) {
            $this->addRow($row);
        }

        return $this;
    }

    public function addRow(array $row)
    {
        $this->rows[] = array_values($row);

        return $this;
    }

    public function setRow(array $row, $column)
    {
        $this->rows[$column] = $row;

        return $this;
    }

    /**
     * Sets background character, used for cell padding.
     *
     * @param string $backgroundChar
     *
     * @return TableHelper
     */
    public function setBackgroundChar($backgroundChar)
    {
        $this->backgroundChar = $backgroundChar;

        return $this;
    }

    /**
     * Sets horizontal border character.
     *
     * @param string $horizontalBorderChar
     *
     * @return TableHelper
     */
    public function setHorizontalBorderChar($horizontalBorderChar)
    {
        $this->horizontalBorderChar = $horizontalBorderChar;

        return $this;
    }

    /**
     * Sets vertical border character.
     *
     * @param string $verticalBorderChar
     *
     * @return TableHelper
     */
    public function setVerticalBorderChar($verticalBorderChar)
    {
        $this->verticalBorderChar = $verticalBorderChar;

        return $this;
    }

    /**
     * Sets edge character.
     *
     * @param string $edgeChar
     *
     * @return TableHelper
     */
    public function setEdgeChar($edgeChar)
    {
        $this->edgeChar = $edgeChar;

        return $this;
    }

    /**
     * Sets header cell format.
     *
     * @param string $cellHeaderFormat
     *
     * @return TableHelper
     */
    public function setCellHeaderFormat($cellHeaderFormat)
    {
        $this->cellHeaderFormat = $cellHeaderFormat;

        return $this;
    }

    /**
     * Sets row cell format.
     *
     * @param string $cellRowFormat
     *
     * @return TableHelper
     */
    public function setCellRowFormat($cellRowFormat)
    {
        $this->cellRowFormat = $cellRowFormat;

        return $this;
    }

    /**
     * Sets table border format.
     *
     * @param string $borderFormat
     *
     * @return TableHelper
     */
    public function setBorderFormat($borderFormat)
    {
        $this->borderFormat = $borderFormat;

        return $this;
    }

    /**
     * Sets cell padding type.
     *
     * @param integer $padType STR_PAD_*
     *
     * @return TableHelper
     */
    public function setPadType($padType)
    {
        $this->padType = $padType;

        return $this;
    }

    /**
     * Renders table to output.
     *
     * Example:
     * +---------------+-----------------------+------------------+
     * | ISBN          | Title                 | Author           |
     * +---------------+-----------------------+------------------+
     * | 99921-58-10-7 | Divine Comedy         | Dante Alighieri  |
     * | 9971-5-0210-0 | A Tale of Two Cities  | Charles Dickens  |
     * | 960-425-059-0 | The Lord of the Rings | J. R. R. Tolkien |
     * +---------------+-----------------------+------------------+
     *
     * @param OutputInterface $output
     */
    public function render(OutputInterface $output)
    {
        $this->output = $output;

        $this->renderRowSeparator();
        $this->renderRow($this->headers, $this->cellHeaderFormat);
        if (!empty($this->headers)) {
            $this->renderRowSeparator();
        }
        foreach ($this->rows as $row) {
            $this->renderRow($row, $this->cellRowFormat);
        }
        if (!empty($this->rows)) {
            $this->renderRowSeparator();
        }

        $this->afterRender();
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'table';
    }

    /**
     * Renders horizontal header separator.
     *
     * Example: +-----+-----------+-------+
     */
    private function renderRowSeparator()
    {
        if (0 === $count = $this->getNumberOfColumns()) {
            return;
        }

        $markup = $this->edgeChar;
        for ($column = 0; $column < $count; $column++) {
            $markup .= str_repeat($this->horizontalBorderChar, $this->getColumnWidth($column))
                    .$this->edgeChar
            ;
        }

        $this->output->writeln(sprintf($this->borderFormat, $markup));
    }

    /**
     * Renders vertical column separator.
     */
    private function renderColumnSeparator()
    {
        $this->output->write(sprintf($this->borderFormat, $this->verticalBorderChar));
    }

    /**
     * Renders table row.
     *
     * Example: | 9971-5-0210-0 | A Tale of Two Cities  | Charles Dickens  |
     *
     * @param array  $row
     * @param string $cellFormat
     */
    private function renderRow(array $row, $cellFormat)
    {
        if (empty($row)) {
            return;
        }

        $this->renderColumnSeparator();
        for ($column = 0, $count = $this->getNumberOfColumns(); $column < $count; $column++) {
            $this->renderCell($row, $column, $cellFormat);
            $this->renderColumnSeparator();
        }
        $this->output->writeln('');
    }

    /**
     * Renders table cell with padding.
     *
     * @param array   $row
     * @param integer $column
     * @param string  $cellFormat
     */
    private function renderCell(array $row, $column, $cellFormat)
    {
        $cell = isset($row[$column]) ? $row[$column] : '';

        $this->output->write(sprintf(
            $cellFormat,
            str_pad(
                $this->backgroundChar.$cell.$this->backgroundChar,
                $this->getColumnWidth($column),
                $this->backgroundChar,
                $this->padType
            )
        ));
    }

    /**
     * Gets number of columns for this table.
     *
     * @return int
     */
    private function getNumberOfColumns()
    {
        if (null !== $this->numberOfColumns) {
            return $this->numberOfColumns;
        }

        $columns = array(0);
        $columns[] = count($this->headers);
        foreach ($this->rows as $row) {
            $columns[] = count($row);
        }

        return $this->numberOfColumns = max($columns);
    }

    /**
     * Gets column width.
     *
     * @param integer $column
     *
     * @return int
     */
    private function getColumnWidth($column)
    {
        if (isset($this->columnWidths[$column])) {
            return $this->columnWidths[$column];
        }

        $lengths = array(0);
        $lengths[] = $this->getCellWidth($this->headers, $column);
        foreach ($this->rows as $row) {
            $lengths[] = $this->getCellWidth($row, $column);
        }

        return $this->columnWidths[$column] = max($lengths) + 2;
    }

    /**
     * Gets cell width.
     *
     * @param array   $row
     * @param integer $column
     *
     * @return int
     */
    private function getCellWidth(array $row, $column)
    {
        if ($column < 0) {
            return 0;
        }

        if (isset($row[$column])) {
            return mb_strlen($row[$column]);
        }

        return $this->getCellWidth($row, $column - 1);
    }

    /**
     * Called after rendering to cleanup cache data.
     */
    private function afterRender()
    {
        $this->columnWidths = array();
        $this->numberOfColumns = null;
    }
}
