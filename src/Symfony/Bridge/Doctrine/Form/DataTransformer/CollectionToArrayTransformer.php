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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @implements DataTransformerInterface<Collection|array, array>
 */
class CollectionToArrayTransformer implements DataTransformerInterface
{
    public function transform(mixed $collection): mixed
    {
        if (null === $collection) {
            return [];
        }

        // For cases when the collection getter returns $collection->toArray()
        // in order to prevent modifications of the returned collection
        if (\is_array($collection)) {
            return $collection;
        }

        if (!$collection instanceof ReadableCollection) {
            throw new TransformationFailedException(\sprintf('Expected a "%s" object.', ReadableCollection::class));
        }

        return $collection->toArray();
    }

    public function reverseTransform(mixed $array): Collection
    {
        if ('' === $array || null === $array) {
            $array = [];
        } else {
            $array = (array) $array;
        }

        return new ArrayCollection($array);
    }
}
