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

use phpDocumentor\Reflection\DocBlock;

/**
 * Extract a property's doc block.
 *
 * A property's doc block may be located on a constructor promoted argument, on
 * the property or on a mutator for that property.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
interface PropertyDocBlockExtractorInterface
{
    /**
     * Gets the first available doc block for a property. It finds the doc block
     * by the following priority:
     *  - constructor promoted argument,
     *  - the class property,
     *  - a mutator method for that property.
     *
     * If no doc block is found, it will return null.
     */
    public function getDocBlock(string $class, string $property): ?DocBlock;
}
