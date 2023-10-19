<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\NameConverter;

/**
 * CamelCase to Underscore name converter.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class CamelCaseToSnakeCaseNameConverter implements NameConverterInterface
{
    /**
     * @param array|null $attributes     The list of attributes to rename or null for all attributes
     * @param bool       $lowerCamelCase Use lowerCamelCase style
     */
    public function __construct(
        private ?array $attributes = null,
        private bool $lowerCamelCase = true,
    ) {
    }

    public function normalize(string $propertyName): string
    {
        if (null === $this->attributes || \in_array($propertyName, $this->attributes)) {
            return strtolower(preg_replace('/[A-Z]/', '_\\0', lcfirst($propertyName)));
        }

        return $propertyName;
    }

    public function denormalize(string $propertyName): string
    {
        $camelCasedName = preg_replace_callback('/(^|_|\.)+(.)/', fn ($match) => ('.' === $match[1] ? '_' : '').strtoupper($match[2]), $propertyName);

        if ($this->lowerCamelCase) {
            $camelCasedName = lcfirst($camelCasedName);
        }

        if (null === $this->attributes || \in_array($camelCasedName, $this->attributes)) {
            return $camelCasedName;
        }

        return $propertyName;
    }
}
