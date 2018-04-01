<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Serializer\Tests\Fixtures;

use Symphony\Component\Serializer\Normalizer\DenormalizableInterface;
use Symphony\Component\Serializer\Normalizer\DenormalizerInterface;

class DenormalizableDummy implements DenormalizableInterface
{
    public function denormalize(DenormalizerInterface $denormalizer, $data, $format = null, array $context = array())
    {
    }
}
