<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\ChoiceList;

/**
 * A choice list for choices of type string or integer.
 *
 * Choices and their associated labels can be passed in a single array. Since
 * choices are passed as array keys, only strings or integer choices are
 * allowed. Choices may also be given as hierarchy of unlimited depth by
 * creating nested arrays. The title of the sub-hierarchy can be stored in the
 * array key pointing to the nested array.
 *
 * <code>
 * $choiceList = new SimpleChoiceList(array(
 *     'creditcard' => 'Credit card payment',
 *     'cash' => 'Cash payment',
 * ));
 * </code>
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since version 2.7, to be removed in 3.0.
 *             Use {@link \Symfony\Component\Form\ChoiceList\ArrayChoiceList} instead.
 */
class SimpleChoiceList extends ChoiceList
{
    /**
     * Creates a new simple choice list.
     *
     * @param array $choices          The array of choices with the choices as keys and
     *                                the labels as values. Choices may also be given
     *                                as hierarchy of unlimited depth by creating nested
     *                                arrays. The title of the sub-hierarchy is stored
     *                                in the array key pointing to the nested array.
     * @param array $preferredChoices A flat array of choices that should be
     *                                presented to the user with priority.
     */
    public function __construct(array $choices, array $preferredChoices = array())
    {
        // Flip preferred choices to speed up lookup
        parent::__construct($choices, $choices, array_flip($preferredChoices));
    }

    /**
     * {@inheritdoc}
     */
    public function getChoicesForValues(array $values)
    {
        $values = $this->fixValues($values);

        // The values are identical to the choices, so we can just return them
        // to improve performance a little bit
        return $this->fixChoices(array_intersect($values, $this->getValues()));
    }

    /**
     * {@inheritdoc}
     */
    public function getValuesForChoices(array $choices)
    {
        $choices = $this->fixChoices($choices);

        // The choices are identical to the values, so we can just return them
        // to improve performance a little bit
        return $this->fixValues(array_intersect($choices, $this->getValues()));
    }

    /**
     * Recursively adds the given choices to the list.
     *
     * Takes care of splitting the single $choices array passed in the
     * constructor into choices and labels.
     *
     * @param array              $bucketForPreferred The bucket where to store the preferred
     *                                               view objects.
     * @param array              $bucketForRemaining The bucket where to store the
     *                                               non-preferred view objects.
     * @param array|\Traversable $choices            The list of choices.
     * @param array              $labels             Ignored.
     * @param array              $preferredChoices   The preferred choices.
     */
    protected function addChoices(array &$bucketForPreferred, array &$bucketForRemaining, $choices, array $labels, array $preferredChoices)
    {
        // Add choices to the nested buckets
        foreach ($choices as $choice => $label) {
            if (is_array($label)) {
                // Don't do the work if the array is empty
                if (count($label) > 0) {
                    $this->addChoiceGroup(
                        $choice,
                        $bucketForPreferred,
                        $bucketForRemaining,
                        $label,
                        $label,
                        $preferredChoices
                    );
                }
            } else {
                $this->addChoice(
                    $bucketForPreferred,
                    $bucketForRemaining,
                    $choice,
                    $label,
                    $preferredChoices
                );
            }
        }
    }

    /**
     * Returns whether the given choice should be preferred judging by the
     * given array of preferred choices.
     *
     * Optimized for performance by treating the preferred choices as array
     * where choices are stored in the keys.
     *
     * @param mixed $choice           The choice to test.
     * @param array $preferredChoices An array of preferred choices.
     *
     * @return bool Whether the choice is preferred.
     */
    protected function isPreferred($choice, array $preferredChoices)
    {
        // Optimize performance over the default implementation
        return isset($preferredChoices[$choice]);
    }

    /**
     * Converts the choice to a valid PHP array key.
     *
     * @param mixed $choice The choice
     *
     * @return string|int A valid PHP array key
     */
    protected function fixChoice($choice)
    {
        return $this->fixIndex($choice);
    }

    /**
     * {@inheritdoc}
     */
    protected function fixChoices(array $choices)
    {
        return $this->fixIndices($choices);
    }

    /**
     * {@inheritdoc}
     */
    protected function createValue($choice)
    {
        // Choices are guaranteed to be unique and scalar, so we can simply
        // convert them to strings
        return (string) $choice;
    }
}
