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
 * @author Fabien Bourigault <bourigaultfabien@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY)]
class SerializedName
{
    /**
     * @param string $serializedName The name of the property as it will be serialized
     */
    public function __construct(
        public readonly string $serializedName,
    ) {
        if ('' === $serializedName) {
            throw new InvalidArgumentException(\sprintf('Parameter given to "%s" must be a non-empty string.', self::class));
        }
    }

    #[\Deprecated('Use the "serializedName" property instead', 'symfony/serializer:7.4')]
    public function getSerializedName(): string
    {
        return $this->serializedName;
    }
}

if (!class_exists(\Symfony\Component\Serializer\Annotation\SerializedName::class, false)) {
    class_alias(SerializedName::class, \Symfony\Component\Serializer\Annotation\SerializedName::class);
}
