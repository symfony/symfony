<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ValueExporter\Formatter;

/**
 * StringFormatter.
 *
 * Returns a string representation of a given value.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
interface StringFormatterInterface extends FormatterInterface
{
    /**
     * Returns a given value formatted to string.
     *
     * @param mixed $value The given value to format to string
     *
     * @return string A string representation of the given value
     */
    public function formatToString($value);
}
