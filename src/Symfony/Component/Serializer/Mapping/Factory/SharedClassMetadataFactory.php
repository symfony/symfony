<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Mapping\Factory;

use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;

/**
 * Keeps the metadata returned by the decorated factory in memory for all the instances created with the same key.
 *
 * @internal
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class SharedClassMetadataFactory implements ClassMetadataFactoryInterface
{
    /**
     * @var array<string, array<string, ClassMetadataInterface>>
     */
    private static array $loaded = [];

    public function __construct(
        private readonly ClassMetadataFactoryInterface $decorated,
        private readonly string $key,
    ) {
    }

    public function getMetadataFor(string|object $value): ClassMetadataInterface
    {
        $class = \is_object($value) ? $value::class : ltrim($value, '\\');

        return self::$loaded[$this->key][$class] ??= $this->decorated->getMetadataFor($value);
    }

    public function hasMetadataFor(mixed $value): bool
    {
        return $this->decorated->hasMetadataFor($value);
    }
}
