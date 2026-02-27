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
class CamelCaseToSnakeCaseNameConverter implements NameConverterInterface
{
    /**
     * Require all properties to be written in snake_case.
     */
    public const REQUIRE_SNAKE_CASE_PROPERTIES = 'require_snake_case_properties';

    /**
     * @param string[]|null $attributes     The list of attributes to rename or null for all attributes
     * @param bool          $lowerCamelCase Use lowerCamelCase style
     */
    public function __construct(
        private ?array $attributes = null,
        private bool $lowerCamelCase = true,
    ) {
    }

    /**
     * @param class-string|null    $class
     * @param array<string, mixed> $context
     */
    public function normalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
    {
        if (null === $this->attributes || \in_array($propertyName, $this->attributes, true)) {
            return strtolower(preg_replace('/[A-Z]/', '_\\0', lcfirst($propertyName)));
        }

        return $propertyName;
    }

    /**
     * @param class-string|null    $class
     * @param array<string, mixed> $context
     */
    public function denormalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
    {
        $class = 1 < \func_num_args() ? func_get_arg(1) : null;
        $format = 2 < \func_num_args() ? func_get_arg(2) : null;
        $context = 3 < \func_num_args() ? func_get_arg(3) : [];

        if (($context[self::REQUIRE_SNAKE_CASE_PROPERTIES] ?? false) && $propertyName !== $this->normalize($propertyName, $class, $format, $context)) {
            throw new UnexpectedPropertyException($propertyName);
        }

        $camelCasedName = preg_replace_callback('/(^|_|\.)++(.)/', static fn ($match) => ('.' === $match[1] ? '_' : '').strtoupper($match[2]), $propertyName);

        if ($this->lowerCamelCase) {
            $camelCasedName = lcfirst($camelCasedName);
        }

        if (null === $this->attributes || \in_array($camelCasedName, $this->attributes, true)) {
            return $camelCasedName;
        }

        return $propertyName;
    }
}
