<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\MapExistingObject;

use Symfony\Component\ObjectMapper\ObjectMapperAwareInterface;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\ObjectMapper\TransformCallableInterface;

/**
 * Simulates a repository lookup finding an already-existing entity and
 * updating it in place via a nested (non-root) map() call.
 *
 * @implements TransformCallableInterface<PostDto, Post>
 */
final class NestedExistingTagTransformer implements TransformCallableInterface, ObjectMapperAwareInterface
{
    private ?ObjectMapperInterface $objectMapper = null;

    public function __construct(
        private readonly Tag $existingTag,
    ) {
    }

    public function withObjectMapper(ObjectMapperInterface $objectMapper): static
    {
        $clone = clone $this;
        $clone->objectMapper = $objectMapper;

        return $clone;
    }

    public function __invoke(mixed $value, object $source, ?object $target): mixed
    {
        return $this->objectMapper->map($value, $this->existingTag);
    }
}
