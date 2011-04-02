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

use Symfony\Component\Form\Util\ChoiceUtil;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

class ArrayToChoicesTransformer implements DataTransformerInterface
{
    public function transform($array)
    {
        if (null === $array) {
            return array();
        }

        if (!is_array($array)) {
            throw new UnexpectedTypeException($array, 'array');
        }

        return ChoiceUtil::toValidChoiceArray($array);
    }

    public function reverseTransform($array)
    {
        if (null === $array) {
            return array();
        }

        if (!is_array($array)) {
            throw new UnexpectedTypeException($array, 'array');
        }

        return $array;
    }
}