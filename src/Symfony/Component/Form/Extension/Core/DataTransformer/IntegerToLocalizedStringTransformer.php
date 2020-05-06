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
 * Transforms between an integer and a localized number with grouping
 * (each thousand) and comma separators.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class IntegerToLocalizedStringTransformer extends NumberToLocalizedStringTransformer
{
    /**
     * Constructs a transformer.
     *
     * @param bool $grouping     Whether thousands should be grouped
     * @param int  $roundingMode One of the ROUND_ constants in this class
     */
    public function __construct(?bool $grouping = false, ?int $roundingMode = \NumberFormatter::ROUND_DOWN)
    {
        parent::__construct(0, $grouping, $roundingMode);
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($value)
    {
        $decimalSeparator = $this->getNumberFormatter()->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);

        if (\is_string($value) && false !== strpos($value, $decimalSeparator)) {
            throw new TransformationFailedException(sprintf('The value "%s" is not a valid integer.', $value));
        }

        $result = parent::reverseTransform($value);

        return null !== $result ? (int) $result : null;
    }

    /**
     * @internal
     */
    protected function castParsedValue($value)
    {
        return $value;
    }
}
