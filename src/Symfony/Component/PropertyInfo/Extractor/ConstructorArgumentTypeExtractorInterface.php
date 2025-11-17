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

use Symfony\Component\TypeInfo\Type;

/**
 * Infers the constructor argument type.
 *
 * @author Dmitrii Poddubnyi <dpoddubny@gmail.com>
 */
interface ConstructorArgumentTypeExtractorInterface
{
    /**
     * Gets type of an argument from constructor.
     *
     * @param class-string $class
     */
    public function getTypeFromConstructor(string $class, string $property): ?Type;
}
