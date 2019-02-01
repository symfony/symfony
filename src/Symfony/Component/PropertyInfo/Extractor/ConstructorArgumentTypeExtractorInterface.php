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
     * @param string $class
     * @param string $property
     *
     * @return Type[]|null
     */
    public function getTypesFromConstructor($class, $property);
}
