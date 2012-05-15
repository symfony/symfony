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
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Util\FormUtil;

class ScalarToBooleanChoicesTransformer implements DataTransformerInterface
{
    private $choiceList;

    /**
     * Constructor.
     *
     * @param ChoiceListInterface $choiceList
     */
    public function __construct(ChoiceListInterface $choiceList)
    {
        $this->choiceList = $choiceList;
    }

    /**
     * Transforms a single choice to a format appropriate for the nested
     * checkboxes/radio buttons.
     *
     * The result is an array with the options as keys and true/false as values,
     * depending on whether a given option is selected. If this field is rendered
     * as select tag, the value is not modified.
     *
     * @param mixed $value An array if "multiple" is set to true, a scalar
     *                       value otherwise.
     *
     * @return mixed         An array
     *
     * @throws UnexpectedTypeException if the given value is not scalar
     * @throws TransformationFailedException if the choices can not be retrieved
     */
    public function transform($value)
    {
        if (!is_scalar($value) && null !== $value) {
            throw new UnexpectedTypeException($value, 'scalar');
        }

        try {
            $choices = $this->choiceList->getChoices();
        } catch (\Exception $e) {
            throw new TransformationFailedException('Can not get the choice list', $e->getCode(), $e);
        }

        $value = FormUtil::toArrayKey($value);
        foreach (array_keys($choices) as $key) {
            $choices[$key] = $key === $value;
        }

        return $choices;
    }

    /**
     * Transforms a checkbox/radio button array to a single choice.
     *
     * The input value is an array with the choices as keys and true/false as
     * values, depending on whether a given choice is selected. The output
     * is the selected choice.
     *
     * @param array $value An array of values
     *
     * @return mixed $value  A scalar value
     *
     * @throws new UnexpectedTypeException if the given value is not an array
     */
    public function reverseTransform($value)
    {
        if (!is_array($value)) {
            throw new UnexpectedTypeException($value, 'array');
        }

        foreach ($value as $choice => $selected) {
            if ($selected) {
                return (string) $choice;
            }
        }

        return null;
    }
}
