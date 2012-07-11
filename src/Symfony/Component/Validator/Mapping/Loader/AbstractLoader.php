<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping\Loader;

use Symfony\Component\Validator\Exception\MappingException;

abstract class AbstractLoader implements LoaderInterface
{
    /**
     * Contains all known namespaces indexed by their prefix
     * @var array
     */
    protected $namespaces;

    /**
     * Adds a namespace alias.
     *
     * @param string $alias     The alias
     * @param string $namespace The PHP namespace
     */
    protected function addNamespaceAlias($alias, $namespace)
    {
        $this->namespaces[$alias] = $namespace;
    }

    /**
     * Creates a new constraint instance for the given constraint name.
     *
     * @param string $name The constraint name. Either a constraint relative
     *                        to the default constraint namespace, or a fully
     *                        qualified class name
     * @param array $options The constraint options
     *
     * @return Constraint
     *
     * @throws MappingException If the namespace prefix is undefined
     */
    protected function newConstraint($name, $options)
    {
        if (strpos($name, '\\') !== false && class_exists($name)) {
            $className = (string) $name;
        } elseif (strpos($name, ':') !== false) {
            list($prefix, $className) = explode(':', $name, 2);

            if (!isset($this->namespaces[$prefix])) {
                throw new MappingException(sprintf('Undefined namespace prefix "%s"', $prefix));
            }

            $className = $this->namespaces[$prefix].$className;
        } else {
            $className = 'Symfony\\Component\\Validator\\Constraints\\'.$name;
        }

        return new $className($options);
    }
}
