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

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\MappingException;

/**
 * Base loader for validation metadata.
 *
 * This loader supports the loading of constraints from Symfony's default
 * namespace (see {@link DEFAULT_NAMESPACE}) using the short class names of
 * those constraints. Constraints can also be loaded using their fully
 * qualified class names. At last, namespace aliases can be defined to load
 * constraints with the syntax "alias:ShortName".
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractLoader implements LoaderInterface
{
    /**
     * The namespace to load constraints from by default.
     */
    public const DEFAULT_NAMESPACE = '\\Symfony\\Component\\Validator\\Constraints\\';

    protected array $namespaces = [];

    /**
     * @var array<class-string, bool>
     */
    private array $namedArgumentsCache = [];

    /**
     * Adds a namespace alias.
     *
     * The namespace alias can be used to reference constraints from specific
     * namespaces in {@link newConstraint()}:
     *
     *     $this->addNamespaceAlias('mynamespace', '\\Acme\\Package\\Constraints\\');
     *
     *     $constraint = $this->newConstraint('mynamespace:NotNull');
     */
    protected function addNamespaceAlias(string $alias, string $namespace): void
    {
        $this->namespaces[$alias] = $namespace;
    }

    /**
     * Creates a new constraint instance for the given constraint name.
     *
     * @param string $name    The constraint name. Either a constraint relative
     *                        to the default constraint namespace, or a fully
     *                        qualified class name. Alternatively, the constraint
     *                        may be preceded by a namespace alias and a colon.
     *                        The namespace alias must have been defined using
     *                        {@link addNamespaceAlias()}.
     * @param mixed  $options The constraint options
     *
     * @throws MappingException If the namespace prefix is undefined
     */
    protected function newConstraint(string $name, mixed $options = null): Constraint
    {
        if (str_contains($name, '\\') && class_exists($name)) {
            $className = $name;
        } elseif (str_contains($name, ':')) {
            [$prefix, $className] = explode(':', $name, 2);

            if (!isset($this->namespaces[$prefix])) {
                throw new MappingException(\sprintf('Undefined namespace prefix "%s".', $prefix));
            }

            $className = $this->namespaces[$prefix].$className;
        } else {
            $className = self::DEFAULT_NAMESPACE.$name;
        }

        if ($this->namedArgumentsCache[$className] ??= (bool) (new \ReflectionMethod($className, '__construct'))->getAttributes(HasNamedArguments::class)) {
            if (null === $options) {
                return new $className();
            }

            if (!\is_array($options)) {
                return new $className($options);
            }

            if (1 === \count($options) && isset($options['value'])) {
                return new $className($options['value']);
            }

            if (array_is_list($options)) {
                return new $className($options);
            }

            return new $className(...$options);
        }

        if ($options) {
            trigger_deprecation('symfony/validator', '7.3', 'Using constraints not supporting named arguments is deprecated. Try adding the HasNamedArguments attribute to %s.', $className);

            return new $className($options);
        }

        return new $className();
    }
}
