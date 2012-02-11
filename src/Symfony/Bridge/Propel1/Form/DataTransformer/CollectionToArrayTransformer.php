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

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\TransformationFailedException;

use \PropelCollection;

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

        if (!$collection instanceof PropelCollection) {
            throw new UnexpectedTypeException($collection, '\PropelCollection');
        }

        // A PropelCollection is ArrayAccess, to cast the collection
        // into array is enough to transform the collection in an array.
        // Never use toArray() on a PropelCollection as it puts all data
        // in array, not just the collection.
        return (array) $collection;
    }

    public function reverseTransform($array)
    {
        $collection = new PropelCollection();

        if ('' === $array || null === $array) {
            return $collection;
        }

        if (!is_array($array)) {
            throw new UnexpectedTypeException($array, 'array');
        }

        $collection->setData($array);

        return $collection;
    }
}
