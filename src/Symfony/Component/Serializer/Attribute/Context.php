<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Attribute;

use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Context
{
    public readonly array $groups;

    /**
     * @param array<string, mixed> $context                The common context to use when serializing or deserializing
     * @param array<string, mixed> $normalizationContext   The context to use when serializing
     * @param array<string, mixed> $denormalizationContext The context to use when deserializing
     * @param string|string[]      $groups                 The groups to use when serializing or deserializing
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        public readonly array $context = [],
        public readonly array $normalizationContext = [],
        public readonly array $denormalizationContext = [],
        string|array $groups = [],
    ) {
        if (!$context && !$normalizationContext && !$denormalizationContext) {
            throw new InvalidArgumentException(\sprintf('At least one of the "context", "normalizationContext", or "denormalizationContext" options must be provided as a non-empty array to "%s".', static::class));
        }

        $this->groups = (array) $groups;

        foreach ($this->groups as $group) {
            if (!\is_string($group)) {
                throw new InvalidArgumentException(\sprintf('Parameter "groups" given to "%s" must be a string or an array of strings, "%s" given.', static::class, get_debug_type($group)));
            }
        }
    }
}
