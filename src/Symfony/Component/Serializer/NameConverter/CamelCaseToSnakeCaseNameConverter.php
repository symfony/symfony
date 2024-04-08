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

use Symfony\Component\Serializer\Exception\UnexpectedPropertyException;

/**
 * CamelCase to Underscore name converter.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Aurélien Pillevesse <aurelienpillevesse@hotmail.fr>
 */
class CamelCaseToSnakeCaseNameConverter implements AdvancedNameConverterInterface
{
    /**
     * Require all properties to be written in snake_case.
     */
    public const REQUIRE_SNAKE_CASE_PROPERTIES = 'require_snake_case_properties';

    /**
     * @param $attributes     The list of attributes to rename or null for all attributes
     * @param $lowerCamelCase Use lowerCamelCase style
     */
    public function __construct(
        private ?array $attributes = null,
        private bool $lowerCamelCase = true,
    ) {
    }

    public function normalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
    {
        if (null === $this->attributes || \in_array($propertyName, $this->attributes, true)) {
            return strtolower(preg_replace('/[A-Z]/', '_\\0', lcfirst($propertyName)));
        }

        return $propertyName;
    }

    public function denormalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
    {
        if (($context[self::REQUIRE_SNAKE_CASE_PROPERTIES] ?? false) && $propertyName !== $this->normalize($propertyName, $class, $format, $context)) {
            throw new UnexpectedPropertyException($propertyName);
        }

        $camelCasedName = preg_replace_callback('/(^|_|\.)+(.)/', fn ($match) => ('.' === $match[1] ? '_' : '').strtoupper($match[2]), $propertyName);

        if ($this->lowerCamelCase) {
            $camelCasedName = lcfirst($camelCasedName);
        }

        if (null === $this->attributes || \in_array($camelCasedName, $this->attributes, true)) {
            return $camelCasedName;
        }

        return $propertyName;
    }
}
