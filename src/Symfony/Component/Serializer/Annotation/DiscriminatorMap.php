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
 * Annotation class for @DiscriminatorMap().
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"CLASS"})
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class DiscriminatorMap
{
    /**
     * @var string
     */
    private $typeProperty;

    /**
     * @var array
     */
    private $mapping;

    /**
     * @param string $typeProperty
     *
     * @throws InvalidArgumentException
     */
    public function __construct($typeProperty, ?array $mapping = null)
    {
        if (\is_array($typeProperty)) {
            trigger_deprecation('symfony/serializer', '5.3', 'Passing an array as first argument to "%s" is deprecated. Use named arguments instead.', __METHOD__);

            $mapping = $typeProperty['mapping'] ?? null;
            $typeProperty = $typeProperty['typeProperty'] ?? null;
        } elseif (!\is_string($typeProperty)) {
            throw new \TypeError(sprintf('"%s": Argument $typeProperty was expected to be a string or array, got "%s".', __METHOD__, get_debug_type($typeProperty)));
        }

        if (empty($typeProperty)) {
            throw new InvalidArgumentException(sprintf('Parameter "typeProperty" of annotation "%s" cannot be empty.', static::class));
        }

        if (empty($mapping)) {
            throw new InvalidArgumentException(sprintf('Parameter "mapping" of annotation "%s" cannot be empty.', static::class));
        }

        $this->typeProperty = $typeProperty;
        $this->mapping = $mapping;
    }

    public function getTypeProperty(): string
    {
        return $this->typeProperty;
    }

    public function getMapping(): array
    {
        return $this->mapping;
    }
}
