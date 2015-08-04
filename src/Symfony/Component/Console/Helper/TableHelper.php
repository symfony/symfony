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
use Symfony\Component\Console\Output\NullOutput;

/**
 * Provides helpers to display table output.
 *
 * @author Саша Стаменковић <umpirsky@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 2.5, to be removed in 3.0
 *             Use {@link Table} instead.
 */
class TableHelper extends Helper
{
    const LAYOUT_DEFAULT = 0;
    const LAYOUT_BORDERLESS = 1;
    const LAYOUT_COMPACT = 2;

    /**
     * @var Table
     */
    private $table;

    public function __construct($triggerDeprecationError = true)
    {
        if ($triggerDeprecationError) {
            @trigger_error('The '.__CLASS__.' class is deprecated since version 2.5 and will be removed in 3.0. Use the Symfony\Component\Console\Helper\Table class instead.', E_USER_DEPRECATED);
        }

        $this->table = new Table(new NullOutput());
    }

    /**
     * Sets table layout type.
     *
     * @param int $layout self::LAYOUT_*
     *
     * @return TableHelper
     *
     * @throws \InvalidArgumentException when the table layout is not known
     */
    public function setLayout($layout)
    {
        switch ($layout) {
            case self::LAYOUT_BORDERLESS:
                $this->table->setStyle('borderless');
                break;

            case self::LAYOUT_COMPACT:
                $this->table->setStyle('compact');
                break;

            case self::LAYOUT_DEFAULT:
                $this->table->setStyle('default');
                break;

            default:
                throw new \InvalidArgumentException(sprintf('Invalid table layout "%s".', $layout));
        };

        return $this;
    }

    public function setHeaders(array $headers)
    {
        $this->table->setHeaders($headers);

        return $this;
    }

    public function setRows(array $rows)
    {
        $this->table->setRows($rows);

        return $this;
    }

    public function addRows(array $rows)
    {
        $this->table->addRows($rows);

        return $this;
    }

    public function addRow(array $row)
    {
        $this->table->addRow($row);

        return $this;
    }

    public function setRow($column, array $row)
    {
        $this->table->setRow($column, $row);

        return $this;
    }

    /**
     * Sets padding character, used for cell padding.
     *
     * @param string $paddingChar
     *
     * @return TableHelper
     */
    public function setPaddingChar($paddingChar)
    {
        $this->table->getStyle()->setPaddingChar($paddingChar);

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
        $this->table->getStyle()->setHorizontalBorderChar($horizontalBorderChar);

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
        $this->table->getStyle()->setVerticalBorderChar($verticalBorderChar);

        return $this;
    }

    /**
     * Sets crossing character.
     *
     * @param string $crossingChar
     *
     * @return TableHelper
     */
    public function setCrossingChar($crossingChar)
    {
        $this->table->getStyle()->setCrossingChar($crossingChar);

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
        $this->table->getStyle()->setCellHeaderFormat($cellHeaderFormat);

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
        $this->table->getStyle()->setCellHeaderFormat($cellRowFormat);

        return $this;
    }

    /**
     * Sets row cell content format.
     *
     * @param string $cellRowContentFormat
     *
     * @return TableHelper
     */
    public function setCellRowContentFormat($cellRowContentFormat)
    {
        $this->table->getStyle()->setCellRowContentFormat($cellRowContentFormat);

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
        $this->table->getStyle()->setBorderFormat($borderFormat);

        return $this;
    }

    /**
     * Sets cell padding type.
     *
     * @param int $padType STR_PAD_*
     *
     * @return TableHelper
     */
    public function setPadType($padType)
    {
        $this->table->getStyle()->setPadType($padType);

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
        $p = new \ReflectionProperty($this->table, 'output');
        $p->setAccessible(true);
        $p->setValue($this->table, $output);

        $this->table->render();
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'table';
    }
}
