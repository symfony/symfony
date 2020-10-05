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

use Symfony\Component\PropertyInfo\Type;

/**
 * Infers the constructor argument type.
 *
 * @author Dmitrii Poddubnyi <dpoddubny@gmail.com>
 *
 * @internal
 */
interface ConstructorArgumentTypeExtractorInterface
{
    /**
     * Gets types of an argument from constructor.
     *
     * @return Type[]|null
     *
     * @internal
     */
    public function getTypesFromConstructor(string $class, string $property): ?array;
}
