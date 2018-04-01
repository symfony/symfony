<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Extension\Core\DataTransformer;

use Symphony\Component\Form\DataTransformerInterface;
use Symphony\Component\Form\Exception\TransformationFailedException;
use Symphony\Component\Form\ChoiceList\ChoiceListInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ChoiceToValueTransformer implements DataTransformerInterface
{
    private $choiceList;

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
        if (null !== $value && !is_string($value)) {
            throw new TransformationFailedException('Expected a string or null.');
        }

        $choices = $this->choiceList->getChoicesForValues(array((string) $value));

        if (1 !== count($choices)) {
            if (null === $value || '' === $value) {
                return;
            }

            throw new TransformationFailedException(sprintf('The choice "%s" does not exist or is not unique', $value));
        }

        return current($choices);
    }
}
