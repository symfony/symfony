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

use Symfony\Component\Form\FormView;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormUtil
{
    /**
     * This class should not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Returns whether the given data is empty.
     *
     * This logic is reused multiple times throughout the processing of
     * a form and needs to be consistent. PHP keyword `empty` cannot
     * be used as it also considers 0 and "0" to be empty.
     *
     * @param mixed $data
     *
     * @return bool
     */
    public static function isEmpty($data)
    {
        // Should not do a check for [] === $data!!!
        // This method is used in occurrences where arrays are
        // not considered to be empty, ever.
        return null === $data || '' === $data;
    }

    /**
     * Appends a block prefix after the static defaults and before dynamic ones.
     */
    public static function appendStaticBlockPrefix(FormView $view, string $prefix, bool $decreaseOffset): void
    {
        // the offset is decreased if more than one dynamic prefix exists, i.e. using the "block_prefix" option
        array_splice($view->vars['block_prefixes'], $decreaseOffset ? -2 : -1, 0, $prefix);
    }
}
