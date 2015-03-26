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

use Symfony\Component\Form\Exception\UnexpectedTypeException;

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
    protected $choices = array();

    /**
     * The values of the choices.
     *
     * @var string[]
     */
    protected $values = array();

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
     * @param array    $choices The selectable choices
     * @param callable $value   The callable for creating the value for a
     *                          choice. If `null` is passed, incrementing
     *                          integers are used as values
     */
    public function __construct(array $choices, $value = null)
    {
        if (null !== $value && !is_callable($value)) {
            throw new UnexpectedTypeException($value, 'null or callable');
        }

        $this->choices = $choices;
        $this->values = array();
        $this->valueCallback = $value;

        if (null === $value) {
            $i = 0;
            foreach ($this->choices as $key => $choice) {
                $this->values[$key] = (string) $i++;
            }
        } else {
            foreach ($choices as $key => $choice) {
                $this->values[$key] = (string) call_user_func($value, $choice);
            }
        }
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
    public function getChoicesForValues(array $values)
    {
        $choices = array();

        foreach ($values as $i => $givenValue) {
            foreach ($this->values as $j => $value) {
                if ($value !== (string) $givenValue) {
                    continue;
                }

                $choices[$i] = $this->choices[$j];
                unset($values[$i]);

                if (0 === count($values)) {
                    break 2;
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
        $values = array();

        // Use the value callback to compare choices by their values, if present
        if ($this->valueCallback) {
            $givenValues = array();

            foreach ($choices as $i => $givenChoice) {
                $givenValues[$i] = (string) call_user_func($this->valueCallback, $givenChoice);
            }

            return array_intersect($givenValues, $this->values);
        }

        // Otherwise compare choices by identity
        foreach ($choices as $i => $givenChoice) {
            foreach ($this->choices as $j => $choice) {
                if ($choice !== $givenChoice) {
                    continue;
                }

                $values[$i] = $this->values[$j];
                unset($choices[$i]);

                if (0 === count($choices)) {
                    break 2;
                }
            }
        }

        return $values;
    }
}
