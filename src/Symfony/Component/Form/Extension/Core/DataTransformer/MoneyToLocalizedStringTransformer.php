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
    private int $divisor;

    public function __construct(?int $scale = 2, ?bool $grouping = true, ?int $roundingMode = \NumberFormatter::ROUND_HALFUP, ?int $divisor = 1, string $locale = null)
    {
        parent::__construct($scale ?? 2, $grouping ?? true, $roundingMode, $locale);

        $this->divisor = $divisor ?? 1;
    }

    /**
     * Transforms a normalized format into a localized money string.
     *
     * @param int|float|null $value Normalized number
     *
     * @throws TransformationFailedException if the given value is not numeric or
     *                                       if the value cannot be transformed
     */
    public function transform(mixed $value): string
    {
        if (null !== $value && 1 !== $this->divisor) {
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
     * @throws TransformationFailedException if the given value is not a string
     *                                       or if the value cannot be transformed
     */
    public function reverseTransform(mixed $value): int|float|null
    {
        $value = parent::reverseTransform($value);
        if (null !== $value && 1 !== $this->divisor) {
            $value = (float) (string) ($value * $this->divisor);
        }

        return $value;
    }
}
