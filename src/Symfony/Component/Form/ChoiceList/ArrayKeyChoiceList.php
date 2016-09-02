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

use Symfony\Component\Form\Exception\InvalidArgumentException;

/**
 * A list of choices that can be stored in the keys of a PHP array.
 *
 * PHP arrays accept only strings and integers as array keys. Other scalar types
 * are cast to integers and strings according to the description of
 * {@link toArrayKey()}. This implementation applies the same casting rules for
 * the choices passed to the constructor and to {@link getValuesForChoices()}.
 *
 * By default, the choices are cast to strings and used as values. Optionally,
 * you may pass custom values. The keys of the value array must match the keys
 * of the choice array.
 *
 * Example:
 *
 * ```php
 * $choices = array('' => 'Don\'t know', 0 => 'No', 1 => 'Yes');
 * $choiceList = new ArrayKeyChoiceList(array_keys($choices));
 *
 * $values = $choiceList->getValues()
 * // => array('', '0', '1')
 *
 * $selectedValues = $choiceList->getValuesForChoices(array(true));
 * // => array('1')
 * ```
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ArrayKeyChoiceList extends ArrayChoiceList
{
    /**
     * Whether the choices are used as values.
     *
     * @var bool
     */
    private $useChoicesAsValues = false;

    /**
     * Casts the given choice to an array key.
     *
     * PHP arrays accept only strings and integers as array keys. Integer
     * strings such as "42" are automatically cast to integers. The boolean
     * values "true" and "false" are cast to the integers 1 and 0. Every other
     * scalar value is cast to a string.
     *
     * @param mixed $choice The choice
     *
     * @return int|string The choice as PHP array key
     *
     * @throws InvalidArgumentException If the choice is not scalar
     *
     * @internal Must not be used outside this class
     */
    public static function toArrayKey($choice)
    {
        if (!is_scalar($choice) && null !== $choice) {
            throw new InvalidArgumentException(sprintf(
                'The value of type "%s" cannot be converted to a valid array key.',
                gettype($choice)
            ));
        }

        if (is_bool($choice) || (string) (int) $choice === (string) $choice) {
            return (int) $choice;
        }

        return (string) $choice;
    }

    /**
     * Creates a list with the given choices and values.
     *
     * The given choice array must have the same array keys as the value array.
     * Each choice must be castable to an integer/string according to the
     * casting rules described in {@link toArrayKey()}.
     *
     * If no values are given, the choices are cast to strings and used as
     * values.
     *
     * @param array|\Traversable $choices The selectable choices
     * @param callable           $value   The callable for creating the value
     *                                    for a choice. If `null` is passed, the
     *                                    choices are cast to strings and used
     *                                    as values
     *
     * @throws InvalidArgumentException If the keys of the choices don't match
     *                                  the keys of the values or if any of the
     *                                  choices is not scalar
     */
    public function __construct($choices, $value = null)
    {
        // If no values are given, use the choices as values
        // Since the choices are stored in the collection keys, i.e. they are
        // strings or integers, we are guaranteed to be able to convert them
        // to strings
        if (null === $value) {
            $value = function ($choice) {
                return (string) $choice;
            };

            $this->useChoicesAsValues = true;
        }

        parent::__construct($choices, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getChoicesForValues(array $values)
    {
        if ($this->useChoicesAsValues) {
            $values = array_map('strval', $values);

            // If the values are identical to the choices, so we can just return
            // them to improve performance a little bit
            return array_map(array(__CLASS__, 'toArrayKey'), array_intersect($values, array_keys($this->choices)));
        }

        return parent::getChoicesForValues($values);
    }

    /**
     * {@inheritdoc}
     */
    public function getValuesForChoices(array $choices)
    {
        $choices = array_map(array(__CLASS__, 'toArrayKey'), $choices);

        if ($this->useChoicesAsValues) {
            // If the choices are identical to the values, we can just return
            // them to improve performance a little bit
            return array_map('strval', array_intersect($choices, $this->choices));
        }

        return parent::getValuesForChoices($choices);
    }

    /**
     * Flattens and flips an array into the given output variable.
     *
     * @param array    $choices          The array to flatten
     * @param callable $value            The callable for generating choice values
     * @param array    $choicesByValues  The flattened choices indexed by the
     *                                   corresponding values
     * @param array    $keysByValues     The original keys indexed by the
     *                                   corresponding values
     * @param array    $structuredValues The values indexed by the original keys
     *
     * @internal Must not be used by user-land code
     */
    protected function flatten(array $choices, $value, &$choicesByValues, &$keysByValues, &$structuredValues)
    {
        if (null === $choicesByValues) {
            $choicesByValues = array();
            $keysByValues = array();
            $structuredValues = array();
        }

        foreach ($choices as $choice => $key) {
            if (is_array($key)) {
                $this->flatten($key, $value, $choicesByValues, $keysByValues, $structuredValues[$choice]);

                continue;
            }

            $choiceValue = (string) call_user_func($value, $choice);
            $choicesByValues[$choiceValue] = $choice;
            $keysByValues[$choiceValue] = $key;
            $structuredValues[$key] = $choiceValue;
        }
    }
}
