<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Form\DataTransformer;

use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityChoiceList;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\DataTransformerInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CollectionToArrayTransformer implements DataTransformerInterface
{
    /**
     * Transforms a collection into an array.
     *
     * @param  Collection|object $collection A collection of entities, a single entity or NULL
     *
     * @return mixed An array of choice keys, a single key or NULL
     */
    public function transform($collection)
    {
        if (null === $collection) {
            return array();
        }

        if (!$collection instanceof Collection) {
            throw new UnexpectedTypeException($collection, 'Doctrine\Common\Collections\Collection');
        }

        return $collection->toArray();
    }

    /**
     * Transforms choice keys into entities.
     *
     * @param  mixed $keys        An array of keys, a single key or NULL
     *
     * @return Collection|object  A collection of entities, a single entity or NULL
     */
    public function reverseTransform($array)
    {
        if ('' === $array || null === $array) {
            $array = array();
        } else {
            $array = (array) $array;
        }

        return new ArrayCollection($array);
    }
}
