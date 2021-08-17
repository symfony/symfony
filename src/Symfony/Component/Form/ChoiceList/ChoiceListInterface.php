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
 * A choice list assigns unique string values to each of a list of choices.
 * These string values are displayed in the "value" attributes in HTML and
 * submitted back to the server.
 *
 * The acceptable data types for the choices depend on the implementation.
 * Values must always be strings and (within the list) free of duplicates.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ChoiceListInterface
{
    /**
     * Returns all selectable choices.
     *
     * @return array The selectable choices indexed by the corresponding values
     */
    public function getChoices();

    /**
     * Returns the values for the choices.
     *
     * The values are strings that do not contain duplicates:
     *
     *     $form->add('field', 'choice', [
     *         'choices' => [
     *             'Decided' => ['Yes' => true, 'No' => false],
     *             'Undecided' => ['Maybe' => null],
     *         ],
     *     ]);
     *
     * In this example, the result of this method is:
     *
     *     [
     *         'Yes' => '0',
     *         'No' => '1',
     *         'Maybe' => '2',
     *     ]
     *
     * Null and false MUST NOT conflict when being casted to string.
     * For this some default incremented values SHOULD be computed.
     *
     * @return string[]
     */
    public function getValues();

    /**
     * Returns the values in the structure originally passed to the list.
     *
     * Contrary to {@link getValues()}, the result is indexed by the original
     * keys of the choices. If the original array contained nested arrays, these
     * nested arrays are represented here as well:
     *
     *     $form->add('field', 'choice', [
     *         'choices' => [
     *             'Decided' => ['Yes' => true, 'No' => false],
     *             'Undecided' => ['Maybe' => null],
     *         ],
     *     ]);
     *
     * In this example, the result of this method is:
     *
     *     [
     *         'Decided' => ['Yes' => '0', 'No' => '1'],
     *         'Undecided' => ['Maybe' => '2'],
     *     ]
     *
     * Nested arrays do not make sense in a view format unless
     * they are used as a convenient way of grouping.
     * If the implementation does not intend to support grouped choices,
     * this method SHOULD be equivalent to {@link getValues()}.
     * The $groupBy callback parameter SHOULD be used instead.
     *
     * @return string[]
     */
    public function getStructuredValues();

    /**
     * Returns the original keys of the choices.
     *
     * The original keys are the keys of the choice array that was passed in the
     * "choice" option of the choice type. Note that this array may contain
     * duplicates if the "choice" option contained choice groups:
     *
     *     $form->add('field', 'choice', [
     *         'choices' => [
     *             'Decided' => [true, false],
     *             'Undecided' => [null],
     *         ],
     *     ]);
     *
     * In this example, the original key 0 appears twice, once for `true` and
     * once for `null`.
     *
     * @return int[]|string[] The original choice keys indexed by the
     *                        corresponding choice values
     */
    public function getOriginalKeys();

    /**
     * Returns the choices corresponding to the given values.
     *
     * The choices are returned with the same keys and in the same order as the
     * corresponding values in the given array.
     *
     * @param string[] $values An array of choice values. Non-existing values in
     *                         this array are ignored
     *
     * @return array
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
     * @return string[]
     */
    public function getValuesForChoices(array $choices);
}
