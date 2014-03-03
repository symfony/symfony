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
     * @return TableStyle
     */
    public function setPaddingChar($paddingChar)
    {
        if (!$paddingChar) {
            throw new \LogicException('The padding char must not be empty');
        }

        $this->paddingChar = $paddingChar;

        return $this;
    }

    /**
     * Gets padding character, used for cell padding.
     *
     * @return string
     */
    public function getPaddingChar()
    {
        return $this->paddingChar;
    }

    /**
     * Sets horizontal border character.
     *
     * @param string $horizontalBorderChar
     *
     * @return TableStyle
     */
    public function setHorizontalBorderChar($horizontalBorderChar)
    {
        $this->horizontalBorderChar = $horizontalBorderChar;

        return $this;
    }

    /**
     * Gets horizontal border character.
     *
     * @return string
     */
    public function getHorizontalBorderChar()
    {
        return $this->horizontalBorderChar;
    }

    /**
     * Sets vertical border character.
     *
     * @param string $verticalBorderChar
     *
     * @return TableStyle
     */
    public function setVerticalBorderChar($verticalBorderChar)
    {
        $this->verticalBorderChar = $verticalBorderChar;

        return $this;
    }

    /**
     * Gets vertical border character.
     *
     * @return string
     */
    public function getVerticalBorderChar()
    {
        return $this->verticalBorderChar;
    }

    /**
     * Sets crossing character.
     *
     * @param string $crossingChar
     *
     * @return TableStyle
     */
    public function setCrossingChar($crossingChar)
    {
        $this->crossingChar = $crossingChar;

        return $this;
    }

    /**
     * Gets crossing character.
     *
     * @return string $crossingChar
     */
    public function getCrossingChar()
    {
        return $this->crossingChar;
    }

    /**
     * Sets header cell format.
     *
     * @param string $cellHeaderFormat
     *
     * @return TableStyle
     */
    public function setCellHeaderFormat($cellHeaderFormat)
    {
        $this->cellHeaderFormat = $cellHeaderFormat;

        return $this;
    }

    /**
     * Gets header cell format.
     *
     * @return string
     */
    public function getCellHeaderFormat()
    {
        return $this->cellHeaderFormat;
    }

    /**
     * Sets row cell format.
     *
     * @param string $cellRowFormat
     *
     * @return TableStyle
     */
    public function setCellRowFormat($cellRowFormat)
    {
        $this->cellRowFormat = $cellRowFormat;

        return $this;
    }

    /**
     * Gets row cell format.
     *
     * @return string
     */
    public function getCellRowFormat()
    {
        return $this->cellRowFormat;
    }

    /**
     * Sets row cell content format.
     *
     * @param string $cellRowContentFormat
     *
     * @return TableStyle
     */
    public function setCellRowContentFormat($cellRowContentFormat)
    {
        $this->cellRowContentFormat = $cellRowContentFormat;

        return $this;
    }

    /**
     * Gets row cell content format.
     *
     * @return string
     */
    public function getCellRowContentFormat()
    {
        return $this->cellRowContentFormat;
    }

    /**
     * Sets table border format.
     *
     * @param string $borderFormat
     *
     * @return TableStyle
     */
    public function setBorderFormat($borderFormat)
    {
        $this->borderFormat = $borderFormat;

        return $this;
    }

    /**
     * Gets table border format.
     *
     * @return string
     */
    public function getBorderFormat()
    {
        return $this->borderFormat;
    }

    /**
     * Sets cell padding type.
     *
     * @param integer $padType STR_PAD_*
     *
     * @return TableStyle
     */
    public function setPadType($padType)
    {
        $this->padType = $padType;

        return $this;
    }

    /**
     * Gets cell padding type.
     *
     * @param integer
     */
    public function getPadType()
    {
        return $this->padType;
    }
}
