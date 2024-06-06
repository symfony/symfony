<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Attribute;

use Symfony\Component\DependencyInjection\Argument\ClassMapArgument;

/**
 * This attribute allows for automatic wiring of a class map from a specified directory that follows the PSR-4 standard.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AutowireClassMap extends Autowire
{
    /**
     * @param string            $namespace     The base namespace for class discovery. Classes within this namespace will be mapped
     * @param string            $path          The directory path where classes are located. The directory structure should follow PSR-4 standards
     * @param class-string|null $instanceOf    An optional parameter to filter and include only classes that implement or extend a specific interface or class
     * @param class-string|null $withAttribute An optional parameter to filter and include only classes that have a specific PHP attribute
     * @param string|null       $indexBy       An optional parameter specifying a static method, property, or constant name to use as the index key for the class map
     */
    public function __construct(
        string $namespace,
        string $path,
        ?string $instanceOf = null,
        ?string $withAttribute = null,
        ?string $indexBy = null,
    ) {
        parent::__construct(new ClassMapArgument($namespace, $path, $instanceOf, $withAttribute, $indexBy));
    }
}
