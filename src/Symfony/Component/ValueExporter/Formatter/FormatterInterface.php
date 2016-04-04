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
 * FormatterInterface.
 *
 * Returns a formatted representation of (a) supported type(s) of PHP value.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
interface FormatterInterface
{
    /**
     * Returns whether the formatter can format the type(s) of the given value.
     *
     * @param mixed $value The given value to format
     *
     * @return bool Whether the given value can be formatted
     */
    public function supports($value);
}
