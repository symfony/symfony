<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping\Factory;

use Symfony\Component\Validator\Mapping\MetadataInterface;

/**
 * Keeps the metadata returned by the decorated factory in memory for all the instances created with the same key.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class SharedMetadataFactory implements MetadataFactoryInterface
{
    /**
     * @var array<string, array<string, MetadataInterface>>
     */
    private static array $loaded = [];

    public function __construct(
        private readonly MetadataFactoryInterface $decorated,
        private readonly string $key,
    ) {
    }

    public function getMetadataFor(mixed $value): MetadataInterface
    {
        if (\is_object($value)) {
            $class = $value::class;
        } elseif (\is_string($value)) {
            $class = ltrim($value, '\\');
        } else {
            return $this->decorated->getMetadataFor($value);
        }

        return self::$loaded[$this->key][$class] ??= $this->decorated->getMetadataFor($value);
    }

    public function hasMetadataFor(mixed $value): bool
    {
        return $this->decorated->hasMetadataFor($value);
    }
}
