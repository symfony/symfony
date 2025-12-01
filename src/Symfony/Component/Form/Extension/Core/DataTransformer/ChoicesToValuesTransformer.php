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

use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @implements DataTransformerInterface<array, array>
 */
class ChoicesToValuesTransformer implements DataTransformerInterface
{
    public function __construct(
        private ChoiceListInterface $choiceList,
    ) {
    }

    public function transform(mixed $value): array
    {
        if (null === $value) {
            return [];
        }

        if (!\is_array($value)) {
            throw new TransformationFailedException('Expected an array.');
        }

        return $this->choiceList->getValuesForChoices($value);
    }

    public function reverseTransform(mixed $value): array
    {
        if (null === $value) {
            return [];
        }

        if (!\is_array($value)) {
            throw new TransformationFailedException('Expected an array.');
        }

        $choices = $this->choiceList->getChoicesForValues($value);

        if (\count($choices) !== \count($value)) {
            throw new TransformationFailedException('Could not find all matching choices for the given values.');
        }

        return $choices;
    }
}
