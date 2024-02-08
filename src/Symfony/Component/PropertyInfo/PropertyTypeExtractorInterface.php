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

/**
 * Type Extractor Interface.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface PropertyTypeExtractorInterface
{
    /**
     * Gets types of a property.
     *
     * @return Type[]|null
     */
    public function getTypes(string $class, string $property, array $context = []): ?array;
}
