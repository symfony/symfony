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

use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Transforms between a normalized format and a localized money string.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class MoneyToLocalizedStringTransformer extends NumberToLocalizedStringTransformer
{

    private $divisor;

    public function __construct($precision = null, $grouping = null, $roundingMode = null, $divisor = null)
    {
        if (null === $grouping) {
            $grouping = true;
        }

        if (null === $precision) {
            $precision = 2;
        }

        parent::__construct($precision, $grouping, $roundingMode);

        if (null === $divisor) {
            $divisor = 1;
        }

        $this->divisor = $divisor;
    }

    /**
     * Transforms a normalized format into a localized money string.
     *
     * @param number $value Normalized number
     *
     * @return string Localized money string.
     *
     * @throws TransformationFailedException If the given value is not numeric or
     *                                       if the value can not be transformed.
     */
    public function transform($value)
    {
        if (null !== $value) {
            if (!is_numeric($value)) {
                throw new TransformationFailedException('Expected a numeric.');
            }

            $value /= $this->divisor;
        }

        return parent::transform($value);
    }

    /**
     * Transforms a localized money string into a normalized format.
     *
     * @param string $value Localized money string
     *
     * @return number Normalized number
     *
     * @throws TransformationFailedException If the given value is not a string
     *                                       or if the value can not be transformed.
     */
    public function reverseTransform($value)
    {
        $value = parent::reverseTransform($value);

        if (null !== $value) {
            $value *= $this->divisor;
        }

        return $value;
    }

}
