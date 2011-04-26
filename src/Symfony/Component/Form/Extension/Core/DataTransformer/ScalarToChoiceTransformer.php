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

use Symfony\Component\Form\Util\FormUtil;
use Symfony\Component\Form\DataTransformerInterface;

class ScalarToChoiceTransformer implements DataTransformerInterface
{
    public function transform($value)
    {
        return FormUtil::toArrayKey($value);
    }

    public function reverseTransform($value)
    {
        return $value;
    }
}