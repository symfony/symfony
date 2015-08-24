<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\ChoiceList\Loader;

use Symfony\Component\Form\ChoiceList\ChoiceListInterface;

/**
 * Loads a choice list.
 *
 * The methods {@link loadChoicesForValues()} and {@link loadValuesForChoices()}
 * can be used to load the list only partially in cases where a fully-loaded
 * list is not necessary.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ChoiceLoaderInterface
{
    /**
     * Loads a list of choices.
     *
     * Optionally, a callable can be passed for generating the choice values.
     * The callable receives the choice as first and the array key as the second
     * argument.
     *
     * @param null|callable $value The callable which generates the values
     *                             from choices
     *
     * @return ChoiceListInterface The loaded choice list
     */
    public function loadChoiceList($value = null);

    /**
     * Loads the choices corresponding to the given values.
     *
     * The choices are returned with the same keys and in the same order as the
     * corresponding values in the given array.
     *
     * Optionally, a callable can be passed for generating the choice values.
     * The callable receives the choice as first and the array key as the second
     * argument.
     *
     * @param string[]      $values An array of choice values. Non-existing
     *                              values in this array are ignored
     * @param null|callable $value  The callable generating the choice values
     *
     * @return array An array of choices
     */
    public function loadChoicesForValues(array $values, $value = null);

    /**
     * Loads the values corresponding to the given choices.
     *
     * The values are returned with the same keys and in the same order as the
     * corresponding choices in the given array.
     *
     * Optionally, a callable can be passed for generating the choice values.
     * The callable receives the choice as first and the array key as the second
     * argument.
     *
     * @param array         $choices An array of choices. Non-existing choices in
     *                               this array are ignored
     * @param null|callable $value   The callable generating the choice values
     *
     * @return string[] An array of choice values
     */
    public function loadValuesForChoices(array $choices, $value = null);
}
