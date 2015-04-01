<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\ChoiceList;

/**
 * A list of choices that can be selected in a choice field.
 *
 * A choice list assigns string values to each of a list of choices. These
 * string values are displayed in the "value" attributes in HTML and submitted
 * back to the server.
 *
 * The acceptable data types for the choices depend on the implementation.
 * Values must always be strings and (within the list) free of duplicates.
 *
 * The choices returned by {@link getChoices()} and the values returned by
 * {@link getValues()} must have the same array indices.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ChoiceListInterface
{
    /**
     * Returns all selectable choices.
     *
     * The keys of the choices correspond to the keys of the values returned by
     * {@link getValues()}.
     *
     * @return array The selectable choices
     */
    public function getChoices();

    /**
     * Returns the values for the choices.
     *
     * The keys of the values correspond to the keys of the choices returned by
     * {@link getChoices()}.
     *
     * @return string[] The choice values
     */
    public function getValues();

    /**
     * Returns the choices corresponding to the given values.
     *
     * The choices are returned with the same keys and in the same order as the
     * corresponding values in the given array.
     *
     * @param string[] $values An array of choice values. Non-existing values in
     *                         this array are ignored
     *
     * @return array An array of choices
     */
    public function getChoicesForValues(array $values);

    /**
     * Returns the values corresponding to the given choices.
     *
     * The values are returned with the same keys and in the same order as the
     * corresponding choices in the given array.
     *
     * @param array $choices An array of choices. Non-existing choices in this
     *                       array are ignored
     *
     * @return string[] An array of choice values
     */
    public function getValuesForChoices(array $choices);
}
