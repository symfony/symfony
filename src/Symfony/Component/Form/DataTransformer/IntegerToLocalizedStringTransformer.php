<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\DataTransformer;

use Symfony\Component\Form\Configurable;
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
        return (int)parent::reverseTransform($value);
    }
}