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
use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * Transforms between an integer and a localized number with grouping
 * (each thousand) and comma separators.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class IntegerToLocalizedStringTransformer extends NumberToLocalizedStringTransformer
{
    /**
     * {@inheritDoc}
     */
    public function reverseTransform($value)
    {
        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        if ('' === $value) {
            return null;
        }

        $formatter = $this->getNumberFormatter();
        $value = $formatter->parse(
            $value,
            PHP_INT_SIZE == 8 ? $formatter::TYPE_INT64 : $formatter::TYPE_INT32
        );

        if (intl_is_failure($formatter->getErrorCode())) {
            throw new TransformationFailedException($formatter->getErrorMessage());
        }

        return $value;
    }
}
