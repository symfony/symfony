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

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ChoiceToValueTransformer implements DataTransformerInterface
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

    public function transform($choice)
    {
        return (string) current($this->choiceList->getValuesForChoices(array($choice)));
    }

    public function reverseTransform($value)
    {
        if (null !== $value && !is_scalar($value)) {
            throw new UnexpectedTypeException($value, 'scalar');
        }

        // These are now valid ChoiceList values, so we can return null
        // right away
        if ($value === '' || $value === null) {
            return null;
        }

        $choices = $this->choiceList->getChoicesForValues(array($value));

        if (count($choices) !== 1) {
            throw new TransformationFailedException('The choice "' . $value . '" does not exist');
        }

        $choice = current($choices);

        return $choice === '' ? null : $choice;
    }
}
