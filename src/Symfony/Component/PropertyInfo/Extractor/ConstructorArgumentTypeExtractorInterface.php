<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Extractor;

use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\TypeInfo\Type;

/**
 * Infers the constructor argument type.
 *
 * @author Dmitrii Poddubnyi <dpoddubny@gmail.com>
 */
interface ConstructorArgumentTypeExtractorInterface
{
    /**
     * Gets types of an argument from constructor.
     *
     * @deprecated since Symfony 7.3, use "getTypeFromConstructor" instead
     *
     * @return LegacyType[]|null
     */
    public function getTypesFromConstructor(string $class, string $property): ?array;

    /**
     * Gets type of an argument from constructor.
     *
     * @param class-string $class
     */
    public function getTypeFromConstructor(string $class, string $property): ?Type;
}
