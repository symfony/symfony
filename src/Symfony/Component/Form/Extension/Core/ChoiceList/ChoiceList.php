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

use Symfony\Component\Form\FormConfigBuilder;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\InvalidConfigurationException;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Extension\Core\View\ChoiceView;

/**
 * A choice list for choices of arbitrary data types.
 *
 * Choices and labels are passed in two arrays. The indices of the choices
 * and the labels should match. Choices may also be given as hierarchy of
 * unlimited depth by creating nested arrays. The title of the sub-hierarchy
 * can be stored in the array key pointing to the nested array. The topmost
 * level of the hierarchy may also be a \Traversable.
 *
 * <code>
 * $choices = array(true, false);
 * $labels = array('Agree', 'Disagree');
 * $choiceList = new ChoiceList($choices, $labels);
 * </code>
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ChoiceList implements ChoiceListInterface
{
    /**
     * The choices with their indices as keys.
     *
     * @var array
     */
    protected $choices = array();

    /**
     * The choice values with the indices of the matching choices as keys.
     *
     * @var array
     */
    protected $values = array();

    /**
     * The preferred view objects as hierarchy containing also the choice groups
     * with the indices of the matching choices as bottom-level keys.
     *
     * @var array
     */
    private $preferredViews = array();

    /**
     * The non-preferred view objects as hierarchy containing also the choice
     * groups with the indices of the matching choices as bottom-level keys.
     *
     * @var array
     */
    private $remainingViews = array();

    /**
     * Creates a new choice list.
     *
     * @param array|\Traversable $choices          The array of choices. Choices may also be given
     *                                             as hierarchy of unlimited depth. Hierarchies are
     *                                             created by creating nested arrays. The title of
     *                                             the sub-hierarchy can be stored in the array
     *                                             key pointing to the nested array. The topmost
     *                                             level of the hierarchy may also be a \Traversable.
     * @param array              $labels           The array of labels. The structure of this array
     *                                             should match the structure of $choices.
     * @param array              $preferredChoices A flat array of choices that should be
     *                                             presented to the user with priority.
     *
     * @throws UnexpectedTypeException If the choices are not an array or \Traversable.
     */
    public function __construct($choices, array $labels, array $preferredChoices = array())
    {
        if (!is_array($choices) && !$choices instanceof \Traversable) {
            throw new UnexpectedTypeException($choices, 'array or \Traversable');
        }

        $this->initialize($choices, $labels, $preferredChoices);
    }

    /**
     * Initializes the list with choices.
     *
     * Safe to be called multiple times. The list is cleared on every call.
     *
     * @param array|\Traversable $choices          The choices to write into the list.
     * @param array              $labels           The labels belonging to the choices.
     * @param array              $preferredChoices The choices to display with priority.
     */
    protected function initialize($choices, array $labels, array $preferredChoices)
    {
        $this->choices = array();
        $this->values = array();
        $this->preferredViews = array();
        $this->remainingViews = array();

        $this->addChoices(
            $this->preferredViews,
            $this->remainingViews,
            $choices,
            $labels,
            $preferredChoices
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getChoices()
    {
        return $this->choices;
    }

    /**
     * {@inheritdoc}
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * {@inheritdoc}
     */
    public function getPreferredViews()
    {
        return $this->preferredViews;
    }

    /**
     * {@inheritdoc}
     */
    public function getRemainingViews()
    {
        return $this->remainingViews;
    }

    /**
     * {@inheritdoc}
     */
    public function getChoicesForValues(array $values)
    {
        $values = $this->fixValues($values);
        $choices = array();

        foreach ($values as $i => $givenValue) {
            foreach ($this->values as $j => $value) {
                if ($value === $givenValue) {
                    $choices[$i] = $this->choices[$j];
                    unset($values[$i]);

                    if (0 === count($values)) {
                        break 2;
                    }
                }
            }
        }

        return $choices;
    }

    /**
     * {@inheritdoc}
     */
    public function getValuesForChoices(array $choices)
    {
        $choices = $this->fixChoices($choices);
        $values = array();

        foreach ($choices as $i => $givenChoice) {
            foreach ($this->choices as $j => $choice) {
                if ($choice === $givenChoice) {
                    $values[$i] = $this->values[$j];
                    unset($choices[$i]);

                    if (0 === count($choices)) {
                        break 2;
                    }
                }
            }
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Deprecated since version 2.4, to be removed in 3.0.
     */
    public function getIndicesForChoices(array $choices)
    {
        $choices = $this->fixChoices($choices);
        $indices = array();

        foreach ($choices as $i => $givenChoice) {
            foreach ($this->choices as $j => $choice) {
                if ($choice === $givenChoice) {
                    $indices[$i] = $j;
                    unset($choices[$i]);

                    if (0 === count($choices)) {
                        break 2;
                    }
                }
            }
        }

        return $indices;
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Deprecated since version 2.4, to be removed in 3.0.
     */
    public function getIndicesForValues(array $values)
    {
        $values = $this->fixValues($values);
        $indices = array();

        foreach ($values as $i => $givenValue) {
            foreach ($this->values as $j => $value) {
                if ($value === $givenValue) {
                    $indices[$i] = $j;
                    unset($values[$i]);

                    if (0 === count($values)) {
                        break 2;
                    }
                }
            }
        }

        return $indices;
    }

    /**
     * Recursively adds the given choices to the list.
     *
     * @param array              $bucketForPreferred The bucket where to store the preferred
     *                                               view objects.
     * @param array              $bucketForRemaining The bucket where to store the
     *                                               non-preferred view objects.
     * @param array|\Traversable $choices            The list of choices.
     * @param array              $labels             The labels corresponding to the choices.
     * @param array              $preferredChoices   The preferred choices.
     *
     * @throws InvalidArgumentException      If the structures of the choices and labels array do not match.
     * @throws InvalidConfigurationException If no valid value or index could be created for a choice.
     */
    protected function addChoices(array &$bucketForPreferred, array &$bucketForRemaining, $choices, array $labels, array $preferredChoices)
    {
        // Add choices to the nested buckets
        foreach ($choices as $group => $choice) {
            if (!array_key_exists($group, $labels)) {
                throw new InvalidArgumentException('The structures of the choices and labels array do not match.');
            }

            if (is_array($choice)) {
                // Don't do the work if the array is empty
                if (count($choice) > 0) {
                    $this->addChoiceGroup(
                        $group,
                        $bucketForPreferred,
                        $bucketForRemaining,
                        $choice,
                        $labels[$group],
                        $preferredChoices
                    );
                }
            } else {
                $this->addChoice(
                    $bucketForPreferred,
                    $bucketForRemaining,
                    $choice,
                    $labels[$group],
                    $preferredChoices
                );
            }
        }
    }

    /**
     * Recursively adds a choice group.
     *
     * @param string $group              The name of the group.
     * @param array  $bucketForPreferred The bucket where to store the preferred
     *                                   view objects.
     * @param array  $bucketForRemaining The bucket where to store the
     *                                   non-preferred view objects.
     * @param array  $choices            The list of choices in the group.
     * @param array  $labels             The labels corresponding to the choices in the group.
     * @param array  $preferredChoices   The preferred choices.
     *
     * @throws InvalidConfigurationException If no valid value or index could be created for a choice.
     */
    protected function addChoiceGroup($group, array &$bucketForPreferred, array &$bucketForRemaining, array $choices, array $labels, array $preferredChoices)
    {
        // If this is a choice group, create a new level in the choice
        // key hierarchy
        $bucketForPreferred[$group] = array();
        $bucketForRemaining[$group] = array();

        $this->addChoices(
            $bucketForPreferred[$group],
            $bucketForRemaining[$group],
            $choices,
            $labels,
            $preferredChoices
        );

        // Remove child levels if empty
        if (empty($bucketForPreferred[$group])) {
            unset($bucketForPreferred[$group]);
        }
        if (empty($bucketForRemaining[$group])) {
            unset($bucketForRemaining[$group]);
        }
    }

    /**
     * Adds a new choice.
     *
     * @param array  $bucketForPreferred The bucket where to store the preferred
     *                                   view objects.
     * @param array  $bucketForRemaining The bucket where to store the
     *                                   non-preferred view objects.
     * @param mixed  $choice             The choice to add.
     * @param string $label              The label for the choice.
     * @param array  $preferredChoices   The preferred choices.
     *
     * @throws InvalidConfigurationException If no valid value or index could be created.
     */
    protected function addChoice(array &$bucketForPreferred, array &$bucketForRemaining, $choice, $label, array $preferredChoices)
    {
        $index = $this->createIndex($choice);

        if ('' === $index || null === $index || !FormConfigBuilder::isValidName((string) $index)) {
            throw new InvalidConfigurationException(sprintf('The index "%s" created by the choice list is invalid. It should be a valid, non-empty Form name.', $index));
        }

        $value = $this->createValue($choice);

        if (!is_string($value)) {
            throw new InvalidConfigurationException(sprintf('The value created by the choice list is of type "%s", but should be a string.', gettype($value)));
        }

        $view = new ChoiceView($choice, $value, $label);

        $this->choices[$index] = $this->fixChoice($choice);
        $this->values[$index] = $value;

        if ($this->isPreferred($choice, $preferredChoices)) {
            $bucketForPreferred[$index] = $view;
        } else {
            $bucketForRemaining[$index] = $view;
        }
    }

    /**
     * Returns whether the given choice should be preferred judging by the
     * given array of preferred choices.
     *
     * Extension point to optimize performance by changing the structure of the
     * $preferredChoices array.
     *
     * @param mixed $choice           The choice to test.
     * @param array $preferredChoices An array of preferred choices.
     *
     * @return bool Whether the choice is preferred.
     */
    protected function isPreferred($choice, array $preferredChoices)
    {
        return in_array($choice, $preferredChoices, true);
    }

    /**
     * Creates a new unique index for this choice.
     *
     * Extension point to change the indexing strategy.
     *
     * @param mixed $choice The choice to create an index for
     *
     * @return int|string A unique index containing only ASCII letters,
     *                    digits and underscores.
     */
    protected function createIndex($choice)
    {
        return count($this->choices);
    }

    /**
     * Creates a new unique value for this choice.
     *
     * By default, an integer is generated since it cannot be guaranteed that
     * all values in the list are convertible to (unique) strings. Subclasses
     * can override this behaviour if they can guarantee this property.
     *
     * @param mixed $choice The choice to create a value for
     *
     * @return string A unique string.
     */
    protected function createValue($choice)
    {
        return (string) count($this->values);
    }

    /**
     * Fixes the data type of the given choice value to avoid comparison
     * problems.
     *
     * @param mixed $value The choice value.
     *
     * @return string The value as string.
     */
    protected function fixValue($value)
    {
        return (string) $value;
    }

    /**
     * Fixes the data types of the given choice values to avoid comparison
     * problems.
     *
     * @param array $values The choice values.
     *
     * @return array The values as strings.
     */
    protected function fixValues(array $values)
    {
        foreach ($values as $i => $value) {
            $values[$i] = $this->fixValue($value);
        }

        return $values;
    }

    /**
     * Fixes the data type of the given choice index to avoid comparison
     * problems.
     *
     * @param mixed $index The choice index.
     *
     * @return int|string The index as PHP array key.
     */
    protected function fixIndex($index)
    {
        if (is_bool($index) || (string) (int) $index === (string) $index) {
            return (int) $index;
        }

        return (string) $index;
    }

    /**
     * Fixes the data types of the given choice indices to avoid comparison
     * problems.
     *
     * @param array $indices The choice indices.
     *
     * @return array The indices as strings.
     */
    protected function fixIndices(array $indices)
    {
        foreach ($indices as $i => $index) {
            $indices[$i] = $this->fixIndex($index);
        }

        return $indices;
    }

    /**
     * Fixes the data type of the given choice to avoid comparison problems.
     *
     * Extension point. In this implementation, choices are guaranteed to
     * always maintain their type and thus can be typesafely compared.
     *
     * @param mixed $choice The choice
     *
     * @return mixed The fixed choice
     */
    protected function fixChoice($choice)
    {
        return $choice;
    }

    /**
     * Fixes the data type of the given choices to avoid comparison problems.
     *
     * @param array $choices The choices.
     *
     * @return array The fixed choices.
     *
     * @see fixChoice()
     */
    protected function fixChoices(array $choices)
    {
        return $choices;
    }
}
