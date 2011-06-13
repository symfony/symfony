<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\DataTransformer;

use Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

class ArrayToBooleanChoicesTransformer implements DataTransformerInterface
{
    private $choiceList;

    public function __construct(ChoiceListInterface $choiceList)
    {
        $this->choiceList = $choiceList;
    }

    /**
     * Transforms an array of choices to a format appropriate for the nested
     * checkboxes/radio buttons.
     *
     * The result is an array with the options as keys and true/false as values,
     * depending on whether a given option is selected. If this field is rendered
     * as select tag, the value is not modified.
     *
     * @param  mixed $array  An array
     *
     * @return mixed         An array
     *
     * @throws UnexpectedTypeException if the given value is not an array
     * @throws TransformationFailedException if the choices can not be retrieved
     */
    public function transform($array)
    {
        if (null === $array) {
            return array();
        }

        if (!is_array($array)) {
            throw new UnexpectedTypeException($array, 'array');
        }

        try {
            $choices = $this->choiceList->getChoices();
        } catch (\Exception $e) {
            throw new TransformationFailedException('Can not get the choice list', $e->getCode(), $e);
        }

        foreach (array_keys($choices) as $key) {
            $choices[$key] = in_array($key, $array, true);
        }

        return $choices;
    }

    /**
     * Transforms a checkbox/radio button array to an array of choices.
     *
     * The input value is an array with the choices as keys and true/false as
     * values, depending on whether a given choice is selected. The output
     * is an array with the selected choices.
     *
     * @param  mixed $value  An array
     *
     * @return mixed $value  An array
     *
     * @throws UnexpectedTypeException if the given value is not an array
     */
    public function reverseTransform($value)
    {
        if (!is_array($value)) {
            throw new UnexpectedTypeException($value, 'array');
        }

        $choices = array();

        foreach ($value as $choice => $selected) {
            if ($selected) {
                $choices[] = $choice;
            }
        }

        return $choices;
    }
}
