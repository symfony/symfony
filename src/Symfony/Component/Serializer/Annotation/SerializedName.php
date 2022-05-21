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
 * Annotation class for @SerializedName().
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"PROPERTY", "METHOD"})
 *
 * @author Fabien Bourigault <bourigaultfabien@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
final class SerializedName
{
    /**
     * @var string[]
     */
    private array $groups;

    /**
     * @param string|string[] $groups
     */
    public function __construct(private string $serializedName, string|array $groups = [])
    {
        $this->groups = (array) $groups;
        if ('' === $serializedName) {
            throw new InvalidArgumentException(sprintf('Parameter of annotation "%s" must be a non-empty string.', static::class));
        }
    }

    public function getSerializedName(): string
    {
        return $this->serializedName;
    }

    /**
     * @return string[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }
}
