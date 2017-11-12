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

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;

/**
 * Defines the styles for a Table.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Саша Стаменковић <umpirsky@gmail.com>
 */
class TableStyle
{
    private $paddingChar = ' ';
    private $horizontalBorderChar = '-';
    private $verticalBorderChar = '|';
    private $crossingChar = '+';
    private $cellHeaderFormat = '<info>%s</info>';
    private $cellRowFormat = '%s';
    private $cellRowContentFormat = ' %s ';
    private $borderFormat = '%s';
    private $padType = STR_PAD_RIGHT;

    /**
     * Sets padding character, used for cell padding.
     *
     * @param string $paddingChar
     *
     * @return $this
     */
    public function setPaddingChar(string $paddingChar)
    {
        if (!$paddingChar) {
            throw new LogicException('The padding char must not be empty');
        }

        $this->paddingChar = $paddingChar;

        return $this;
    }

    /**
     * Gets padding character, used for cell padding.
     *
     * @return string
     */
    public function getPaddingChar(): string
    {
        return $this->paddingChar;
    }

    /**
     * Sets horizontal border character.
     *
     * @param string $horizontalBorderChar
     *
     * @return $this
     */
    public function setHorizontalBorderChar(string $horizontalBorderChar)
    {
        $this->horizontalBorderChar = $horizontalBorderChar;

        return $this;
    }

    /**
     * Gets horizontal border character.
     *
     * @return string
     */
    public function getHorizontalBorderChar(): string
    {
        return $this->horizontalBorderChar;
    }

    /**
     * Sets vertical border character.
     *
     * @param string $verticalBorderChar
     *
     * @return $this
     */
    public function setVerticalBorderChar(string $verticalBorderChar)
    {
        $this->verticalBorderChar = $verticalBorderChar;

        return $this;
    }

    /**
     * Gets vertical border character.
     *
     * @return string
     */
    public function getVerticalBorderChar(): string
    {
        return $this->verticalBorderChar;
    }

    /**
     * Sets crossing character.
     *
     * @param string $crossingChar
     *
     * @return $this
     */
    public function setCrossingChar(string $crossingChar)
    {
        $this->crossingChar = $crossingChar;

        return $this;
    }

    /**
     * Gets crossing character.
     *
     * @return string $crossingChar
     */
    public function getCrossingChar(): string
    {
        return $this->crossingChar;
    }

    /**
     * Sets header cell format.
     *
     * @param string $cellHeaderFormat
     *
     * @return $this
     */
    public function setCellHeaderFormat(string $cellHeaderFormat)
    {
        $this->cellHeaderFormat = $cellHeaderFormat;

        return $this;
    }

    /**
     * Gets header cell format.
     *
     * @return string
     */
    public function getCellHeaderFormat(): string
    {
        return $this->cellHeaderFormat;
    }

    /**
     * Sets row cell format.
     *
     * @param string $cellRowFormat
     *
     * @return $this
     */
    public function setCellRowFormat(string $cellRowFormat)
    {
        $this->cellRowFormat = $cellRowFormat;

        return $this;
    }

    /**
     * Gets row cell format.
     *
     * @return string
     */
    public function getCellRowFormat(): string
    {
        return $this->cellRowFormat;
    }

    /**
     * Sets row cell content format.
     *
     * @param string $cellRowContentFormat
     *
     * @return $this
     */
    public function setCellRowContentFormat(string $cellRowContentFormat)
    {
        $this->cellRowContentFormat = $cellRowContentFormat;

        return $this;
    }

    /**
     * Gets row cell content format.
     *
     * @return string
     */
    public function getCellRowContentFormat(): string
    {
        return $this->cellRowContentFormat;
    }

    /**
     * Sets table border format.
     *
     * @param string $borderFormat
     *
     * @return $this
     */
    public function setBorderFormat(string $borderFormat)
    {
        $this->borderFormat = $borderFormat;

        return $this;
    }

    /**
     * Gets table border format.
     *
     * @return string
     */
    public function getBorderFormat(): string
    {
        return $this->borderFormat;
    }

    /**
     * Sets cell padding type.
     *
     * @param int $padType STR_PAD_*
     *
     * @return $this
     */
    public function setPadType(int $padType)
    {
        if (!in_array($padType, array(STR_PAD_LEFT, STR_PAD_RIGHT, STR_PAD_BOTH), true)) {
            throw new InvalidArgumentException('Invalid padding type. Expected one of (STR_PAD_LEFT, STR_PAD_RIGHT, STR_PAD_BOTH).');
        }

        $this->padType = $padType;

        return $this;
    }

    /**
     * Gets cell padding type.
     *
     * @return int
     */
    public function getPadType(): int
    {
        return $this->padType;
    }
}
