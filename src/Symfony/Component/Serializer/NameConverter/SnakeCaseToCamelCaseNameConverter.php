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
 * Underscore to camelCase name converter.
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
final class SnakeCaseToCamelCaseNameConverter implements NameConverterInterface
{
    /**
     * Require all properties to be written in camelCase.
     */
    public const REQUIRE_CAMEL_CASE_PROPERTIES = 'require_camel_case_properties';

    /**
     * @param string[]|null $attributes     The list of attributes to rename or null for all attributes
     * @param bool          $lowerCamelCase Use lowerCamelCase style
     */
    public function __construct(
        private readonly ?array $attributes = null,
        private readonly bool $lowerCamelCase = true,
    ) {
    }

    /**
     * @param class-string|null    $class
     * @param array<string, mixed> $context
     */
    public function normalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
    {
        if (null !== $this->attributes && !\in_array($propertyName, $this->attributes, true)) {
            return $propertyName;
        }

        $camelCasedName = preg_replace_callback(
            '/(^|_|\.)+(.)/',
            fn ($match) => ('.' === $match[1] ? '_' : '').strtoupper($match[2]),
            $propertyName
        );

        if ($this->lowerCamelCase) {
            $camelCasedName = lcfirst($camelCasedName);
        }

        return $camelCasedName;
    }

    /**
     * @param class-string|null    $class
     * @param array<string, mixed> $context
     */
    public function denormalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
    {
        if (($context[self::REQUIRE_CAMEL_CASE_PROPERTIES] ?? false) && $propertyName !== $this->normalize($propertyName, $class, $format, $context)) {
            throw new UnexpectedPropertyException($propertyName);
        }

        $snakeCased = strtolower(preg_replace('/[A-Z]/', '_\\0', lcfirst($propertyName)));
        if (null === $this->attributes || \in_array($snakeCased, $this->attributes, true)) {
            return $snakeCased;
        }

        return $propertyName;
    }
}
