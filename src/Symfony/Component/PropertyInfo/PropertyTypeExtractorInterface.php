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

use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\TypeInfo\Type;

/**
 * Type Extractor Interface.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @method Type|null getType(string $class, string $property, array $context = [])
 */
interface PropertyTypeExtractorInterface
{
    /**
     * Gets types of a property.
     *
     * @deprecated since Symfony 7.3, use "getType" instead
     *
     * @return LegacyType[]|null
     */
    public function getTypes(string $class, string $property, array $context = []): ?array;
}
