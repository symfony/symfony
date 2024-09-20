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
 * @implements DataTransformerInterface<mixed, string>
 */
class ChoiceToValueTransformer implements DataTransformerInterface
{
    public function __construct(
        private ChoiceListInterface $choiceList,
    ) {
    }

    public function transform(mixed $choice): mixed
    {
        return (string) current($this->choiceList->getValuesForChoices([$choice]));
    }

    public function reverseTransform(mixed $value): mixed
    {
        if (null !== $value && !\is_string($value)) {
            throw new TransformationFailedException('Expected a string or null.');
        }

        $choices = $this->choiceList->getChoicesForValues([(string) $value]);

        if (1 !== \count($choices)) {
            if (null === $value || '' === $value) {
                return null;
            }

            throw new TransformationFailedException(\sprintf('The choice "%s" does not exist or is not unique.', $value));
        }

        return current($choices);
    }
}
