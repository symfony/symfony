<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Annotation;

use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * Annotation class for @Context().
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"PROPERTY", "METHOD"})
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Context
{
    private array $groups;

    /**
     * @param string|string[] $groups
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        private array $context = [],
        private array $normalizationContext = [],
        private array $denormalizationContext = [],
        string|array $groups = [],
    ) {
        if (!$context && !$normalizationContext && !$denormalizationContext) {
            throw new InvalidArgumentException(sprintf('At least one of the "context", "normalizationContext", or "denormalizationContext" options of annotation "%s" must be provided as a non-empty array.', static::class));
        }

        $this->groups = (array) $groups;

        foreach ($this->groups as $group) {
            if (!\is_string($group)) {
                throw new InvalidArgumentException(sprintf('Parameter "groups" of annotation "%s" must be a string or an array of strings. Got "%s".', static::class, get_debug_type($group)));
            }
        }
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getNormalizationContext(): array
    {
        return $this->normalizationContext;
    }

    public function getDenormalizationContext(): array
    {
        return $this->denormalizationContext;
    }

    public function getGroups(): array
    {
        return $this->groups;
    }
}
