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
 * @implements DataTransformerInterface<Collection, array>
 */
class CollectionToArrayTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): mixed
    {
        if (null === $value) {
            return [];
        }

        // For cases when the collection getter returns $collection->toArray()
        // in order to prevent modifications of the returned collection
        if (\is_array($value)) {
            return $value;
        }

        if (!$value instanceof ReadableCollection) {
            throw new TransformationFailedException(\sprintf('Expected a "%s" object.', ReadableCollection::class));
        }

        return $value->toArray();
    }

    public function reverseTransform(mixed $value): Collection
    {
        if ('' === $value || null === $value) {
            $value = [];
        } else {
            $value = (array) $value;
        }

        return new ArrayCollection($value);
    }
}
