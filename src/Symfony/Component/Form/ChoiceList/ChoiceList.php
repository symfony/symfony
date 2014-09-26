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
 * A list of choices with arbitrary data types.
 *
 * The user of this class is responsible for assigning string values to the
 * choices. Both the choices and their values are passed to the constructor.
 * Each choice must have a corresponding value (with the same array key) in
 * the value array.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ChoiceList implements ChoiceListInterface
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
     * Creates a list with the given choices and values.
     *
     * The given choice array must have the same array keys as the value array.
     *
     * @param array    $choices The selectable choices
     * @param string[] $values  The string values of the choices
     *
     * @throws InvalidArgumentException If the keys of the choices don't match
     *                                  the keys of the values
     */
    public function __construct(array $choices, array $values)
    {
        $choiceKeys = array_keys($choices);
        $valueKeys = array_keys($values);

        if ($choiceKeys !== $valueKeys) {
            throw new InvalidArgumentException(sprintf(
                'The keys of the choices and the values must match. The choice '.
                'keys are: "%s". The value keys are: "%s".',
                implode('", "', $choiceKeys),
                implode('", "', $valueKeys)
            ));
        }

        $this->choices = $choices;
        $this->values = array_map('strval', $values);
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
