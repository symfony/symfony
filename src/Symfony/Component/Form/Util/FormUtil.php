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

abstract class FormUtil
{
    /**
     * Returns whether the given choice is a group.
     *
     * @param mixed $choice A choice
     *
     * @return Boolean Whether the choice is a group
     */
    static public function isChoiceGroup($choice)
    {
        return is_array($choice) || $choice instanceof \Traversable;
    }

    /**
     * Returns whether the given choice is selected.
     *
     * @param mixed $choice The choice
     * @param mixed $value  the value
     *
     * @return Boolean Whether the choice is selected
     */
    static public function isChoiceSelected($choice, $value)
    {
        if (is_array($value)) {
            return false !== array_search($choice, $value, true);
        }

        return $choice === $value;
    }
}
