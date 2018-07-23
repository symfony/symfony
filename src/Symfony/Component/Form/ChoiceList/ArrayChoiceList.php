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
 * A list of choices with arbitrary data types.
 *
 * The user of this class is responsible for assigning string values to the
 * choices. Both the choices and their values are passed to the constructor.
 * Each choice must have a corresponding value (with the same array key) in
 * the value array.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ArrayChoiceList implements ChoiceListInterface
{
    /**
     * The choices in the list.
     *
     * @var array
     */
    protected $choices;

    /**
     * The values indexed by the original keys.
     *
     * @var array
     */
    protected $structuredValues;

    /**
     * The original keys of the choices array.
     *
     * @var int[]|string[]
     */
    protected $originalKeys;

    /**
     * The callback for creating the value for a choice.
     *
     * @var callable
     */
    protected $valueCallback;

    /**
     * Creates a list with the given choices and values.
     *
     * The given choice array must have the same array keys as the value array.
     *
     * @param iterable      $choices The selectable choices
     * @param callable|null $value   The callable for creating the value
     *                               for a choice. If `null` is passed,
     *                               incrementing integers are used as
     *                               values
     */
    public function __construct(iterable $choices, callable $value = null)
    {
        if ($choices instanceof \Traversable) {
            $choices = iterator_to_array($choices);
        }

        if (null === $value && $this->castableToString($choices)) {
            $value = function ($choice) {
                return false === $choice ? '0' : (string) $choice;
            };
        }

        if (null !== $value) {
            // If a deterministic value generator was passed, use it later
            $this->valueCallback = $value;
        } else {
            // Otherwise simply generate incrementing integers as values
            $i = 0;
            $value = function () use (&$i) {
                return $i++;
            };
        }

        // If the choices are given as recursive array (i.e. with explicit
        // choice groups), flatten the array. The grouping information is needed
        // in the view only.
        $this->flatten($choices, $value, $choicesByValues, $keysByValues, $structuredValues);

        $this->choices = $choicesByValues;
        $this->originalKeys = $keysByValues;
        $this->structuredValues = $structuredValues;
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
        return array_map('strval', array_keys($this->choices));
    }

    /**
     * {@inheritdoc}
     */
    public function getStructuredValues()
    {
        return $this->structuredValues;
    }

    /**
     * {@inheritdoc}
     */
    public function getOriginalKeys()
    {
        return $this->originalKeys;
    }

    /**
     * {@inheritdoc}
     */
    public function getChoicesForValues(array $values)
    {
        $choices = array();

        foreach ($values as $i => $givenValue) {
            if (array_key_exists($givenValue, $this->choices)) {
                $choices[$i] = $this->choices[$givenValue];
            }
        }

        return $choices;
    }

    /**
     * {@inheritdoc}
     */
    public function getValuesForChoices(array $choices)
    {
        $values = array();

        // Use the value callback to compare choices by their values, if present
        if ($this->valueCallback) {
            $givenValues = array();

            foreach ($choices as $i => $givenChoice) {
                $givenValues[$i] = (string) call_user_func($this->valueCallback, $givenChoice);
            }

            return array_intersect($givenValues, array_keys($this->choices));
        }

        // Otherwise compare choices by identity
        foreach ($choices as $i => $givenChoice) {
            foreach ($this->choices as $value => $choice) {
                if ($choice === $givenChoice) {
                    $values[$i] = (string) $value;
                    break;
                }
            }
        }

        return $values;
    }

    /**
     * Flattens an array into the given output variables.
     *
     * @param array    $choices          The array to flatten
     * @param callable $value            The callable for generating choice values
     * @param array    $choicesByValues  The flattened choices indexed by the
     *                                   corresponding values
     * @param array    $keysByValues     The original keys indexed by the
     *                                   corresponding values
     * @param array    $structuredValues The values indexed by the original keys
     *
     * @internal
     */
    protected function flatten(array $choices, $value, &$choicesByValues, &$keysByValues, &$structuredValues)
    {
        if (null === $choicesByValues) {
            $choicesByValues = array();
            $keysByValues = array();
            $structuredValues = array();
        }

        foreach ($choices as $key => $choice) {
            if (is_array($choice)) {
                $this->flatten($choice, $value, $choicesByValues, $keysByValues, $structuredValues[$key]);

                continue;
            }

            $choiceValue = (string) call_user_func($value, $choice);
            $choicesByValues[$choiceValue] = $choice;
            $keysByValues[$choiceValue] = $key;
            $structuredValues[$key] = $choiceValue;
        }
    }

    /**
     * Checks whether the given choices can be cast to strings without
     * generating duplicates.
     *
     * @param array      $choices The choices
     * @param array|null $cache   The cache for previously checked entries. Internal
     *
     * @return bool returns true if the choices can be cast to strings and
     *              false otherwise
     */
    private function castableToString(array $choices, array &$cache = array())
    {
        foreach ($choices as $choice) {
            if (is_array($choice)) {
                if (!$this->castableToString($choice, $cache)) {
                    return false;
                }

                continue;
            } elseif (!is_scalar($choice)) {
                return false;
            }

            $choice = false === $choice ? '0' : (string) $choice;

            if (isset($cache[$choice])) {
                return false;
            }

            $cache[$choice] = true;
        }

        return true;
    }
}
