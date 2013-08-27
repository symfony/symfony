<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Util;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormUtil
{
    /**
     * This class should not be instantiated
     */
    private function __construct() {}

    /**
     * Returns whether the given data is empty.
     *
     * This logic is reused multiple times throughout the processing of
     * a form and needs to be consistent. PHP's keyword `empty` cannot
     * be used as it also considers 0 and "0" to be empty.
     *
     * @param  mixed $data
     *
     * @return Boolean
     */
    public static function isEmpty($data)
    {
        // Should not do a check for array() === $data!!!
        // This method is used in occurrences where arrays are
        // not considered to be empty, ever.
        return null === $data || '' === $data;
    }

    /**
     * Makes a technical name human readable.
     *
     * Sequences of underscores are replaced by single spaces. The first letter
     * of the resulting string is capitalized, while all other letters are
     * turned to lowercase.
     *
     * @param string $text The text to humanize.
     *
     * @return string The humanized text.
     */
    public static function humanize($text)
    {
        return ucfirst(trim(strtolower(preg_replace(array('/([A-Z])/', '/[_\s]+/'), array('_$1', ' '), $text))));
    }
}
