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
 * Defines the interface for property name converters.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface NameConverterInterface
{
    /**
     * Converts a property name to its normalized value.
     *
     * @param class-string|null    $class
     * @param string|null          $format
     * @param array<string, mixed> $context
     */
    public function normalize(string $propertyName/* , ?string $class = null, ?string $format = null, array $context = [] */): string;

    /**
     * Converts a property name to its denormalized value.
     *
     * @param class-string|null    $class
     * @param string|null          $format
     * @param array<string, mixed> $context
     */
    public function denormalize(string $propertyName/* , ?string $class = null, ?string $format = null, array $context = [] */): string;
}
