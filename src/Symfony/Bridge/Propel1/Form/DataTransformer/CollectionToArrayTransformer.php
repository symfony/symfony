<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Propel1\Form\DataTransformer;

use PropelObjectCollection;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * CollectionToArrayTransformer class.
 *
 * @author William Durand <william.durand1@gmail.com>
 * @author Pierre-Yves Lebecq <py.lebecq@gmail.com>
 */
class CollectionToArrayTransformer implements DataTransformerInterface
{
    public function transform($collection)
    {
        if (null === $collection) {
            return array();
        }

        if (!$collection instanceof PropelObjectCollection) {
            throw new TransformationFailedException('Expected a \PropelObjectCollection.');
        }

        return $collection->getData();
    }

    public function reverseTransform($array)
    {
        $collection = new PropelObjectCollection();

        if ('' === $array || null === $array) {
            return $collection;
        }

        if (!is_array($array)) {
            throw new TransformationFailedException('Expected an array.');
        }

        $collection->setData($array);

        return $collection;
    }
}
