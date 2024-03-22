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
     * @param bool        $grouping     Whether thousands should be grouped
     * @param int|null    $roundingMode One of the ROUND_ constants in this class
     * @param string|null $locale       locale used for transforming
     */
    public function __construct(?bool $grouping = false, ?int $roundingMode = \NumberFormatter::ROUND_DOWN, ?string $locale = null)
    {
        parent::__construct(0, $grouping, $roundingMode, $locale);
    }

    public function reverseTransform(mixed $value): int|float|null
    {
        $decimalSeparator = $this->getNumberFormatter()->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);

        if (\is_string($value) && str_contains($value, $decimalSeparator)) {
            throw new TransformationFailedException(sprintf('The value "%s" is not a valid integer.', $value));
        }

        $result = parent::reverseTransform($value);

        return null !== $result ? (int) $result : null;
    }

    /**
     * @internal
     */
    protected function castParsedValue(int|float $value): int|float
    {
        return $value;
    }
}
