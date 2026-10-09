<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\ObjectMapper\Transform;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\ObjectMapper\ObjectMapperAwareInterface;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\ObjectMapper\Transform\MapCollection;
use Symfony\Component\ObjectMapper\TransformCallableInterface;

/**
 * @template T of object
 *
 * @implements TransformCallableInterface<object, T>
 */
class IterableToArrayCollection implements TransformCallableInterface, ObjectMapperAwareInterface
{
    public function __construct(private MapCollection $mapCollection)
    {
    }

    public function withObjectMapper(ObjectMapperInterface $objectMapper): static
    {
        $clone = clone $this;
        $clone->mapCollection = $this->mapCollection->withObjectMapper($objectMapper);

        return $clone;
    }

    public function __invoke(mixed $value, object $source, ?object $target): mixed
    {
        return new ArrayCollection(($this->mapCollection)($value, $source, $target));
    }
}
