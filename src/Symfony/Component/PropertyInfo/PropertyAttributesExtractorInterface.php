<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo;

interface PropertyAttributesExtractorInterface
{
    /**
     * Gets the attributes of the property.
     *
     * Returns an array of attributes, each attribute is an associative array with the following keys:
     * - name: The fully-qualified class name of the attribute
     * - arguments: An associative array of attribute arguments
     *
     * Example:
     * [['name' => 'FQCN', 'arguments' => ['key' => 'value']], ...]
     */
    public function getAttributes(string $class, string $property, array $context = []): ?array;
}
