<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * ArrayLoader loads array service definitions.
 *
 * The array format does not support anonymous services (cf. the XML loader).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Christophe Coevoet <stof@notk.org>
 */
class ArrayLoader extends Loader
{
    private $container;

    /**
     * Constructor.
     *
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function __construct(ContainerBuilder $container)
    {
        $this->container = $container;
    }

    /**
     * Loads an array config.
     *
     * This loader does not register a resource in the ContainerBuilder as it does not know where the
     * array is coming from. The caller should be responsible for it.
     *
     * @param array  $content The resource
     * @param string $type    The resource type
     */
    public function load($content, $type = null)
    {
        $this->validate($content);

        // empty file
        if (null === $content) {
            return;
        }

        // imports
        $this->parseImports($content);

        // parameters
        if (isset($content['parameters'])) {
            if (!is_array($content['parameters'])) {
                throw new InvalidArgumentException('The "parameters" key should contain an array.');
            }

            foreach ($content['parameters'] as $key => $value) {
                $this->container->setParameter($key, $this->resolveServices($value));
            }
        }

        // extensions
        $this->loadFromExtensions($content);

        // services
        $this->parseDefinitions($content);
    }

    /**
     * Returns true if this class supports the given resource.
     *
     * @param mixed  $resource A resource
     * @param string $type     The resource type
     *
     * @return bool    true if this class supports the given resource, false otherwise
     */
    public function supports($resource, $type = null)
    {
        return is_array($resource);
    }

    /**
     * Parses all imports
     *
     * @param array $content
     */
    private function parseImports($content)
    {
        if (!isset($content['imports'])) {
            return;
        }

        if (!is_array($content['imports'])) {
            throw new InvalidArgumentException('The "imports" key should contain an array.');
        }

        foreach ($content['imports'] as $import) {
            if (!is_array($import)) {
                throw new InvalidArgumentException('The values in the "imports" key should be arrays.');
            }

            $this->import($import['resource'], null);
        }
    }

    /**
     * Parses definitions
     *
     * @param array $content
     */
    private function parseDefinitions($content)
    {
        if (!isset($content['services'])) {
            return;
        }

        if (!is_array($content['services'])) {
            throw new InvalidArgumentException('The "services" key should contain an array.');
        }

        foreach ($content['services'] as $id => $service) {
            $this->parseDefinition($id, $service);
        }
    }

    /**
     * Parses a definition.
     *
     * @param string $id
     * @param array  $service
     *
     * @throws InvalidArgumentException When tags are invalid
     */
    private function parseDefinition($id, $service)
    {
        if (is_string($service) && 0 === strpos($service, '@')) {
            $this->container->setAlias($id, substr($service, 1));

            return;
        }

        if (!is_array($service)) {
            throw new InvalidArgumentException(sprintf('A service definition must be an array or a string starting with "@" but %s found for service "%s".', gettype($service), $id));
        }

        if (isset($service['alias'])) {
            $public = !array_key_exists('public', $service) || (bool) $service['public'];
            $this->container->setAlias($id, new Alias($service['alias'], $public));

            return;
        }

        if (isset($service['parent'])) {
            $definition = new DefinitionDecorator($service['parent']);
        } else {
            $definition = new Definition();
        }

        if (isset($service['class'])) {
            $definition->setClass($service['class']);
        }

        if (isset($service['scope'])) {
            $definition->setScope($service['scope']);
        }

        if (isset($service['synthetic'])) {
            $definition->setSynthetic($service['synthetic']);
        }

        if (isset($service['synchronized'])) {
            $definition->setSynchronized($service['synchronized']);
        }

        if (isset($service['lazy'])) {
            $definition->setLazy($service['lazy']);
        }

        if (isset($service['public'])) {
            $definition->setPublic($service['public']);
        }

        if (isset($service['abstract'])) {
            $definition->setAbstract($service['abstract']);
        }

        if (isset($service['factory_class'])) {
            $definition->setFactoryClass($service['factory_class']);
        }

        if (isset($service['factory_method'])) {
            $definition->setFactoryMethod($service['factory_method']);
        }

        if (isset($service['factory_service'])) {
            $definition->setFactoryService($service['factory_service']);
        }

        if (isset($service['file'])) {
            $definition->setFile($service['file']);
        }

        if (isset($service['arguments'])) {
            $definition->setArguments($this->resolveServices($service['arguments']));
        }

        if (isset($service['properties'])) {
            $definition->setProperties($this->resolveServices($service['properties']));
        }

        if (isset($service['configurator'])) {
            if (is_string($service['configurator'])) {
                $definition->setConfigurator($service['configurator']);
            } else {
                $definition->setConfigurator(array($this->resolveServices($service['configurator'][0]), $service['configurator'][1]));
            }
        }

        if (isset($service['calls'])) {
            if (!is_array($service['calls'])) {
                throw new InvalidArgumentException(sprintf('Parameter "calls" must be an array for service "%s".', $id));
            }

            foreach ($service['calls'] as $call) {
                $args = isset($call[1]) ? $this->resolveServices($call[1]) : array();
                $definition->addMethodCall($call[0], $args);
            }
        }

        if (isset($service['tags'])) {
            if (!is_array($service['tags'])) {
                throw new InvalidArgumentException(sprintf('Parameter "tags" must be an array for service "%s".', $id));
            }

            foreach ($service['tags'] as $tag) {
                if (!is_array($tag)) {
                    throw new InvalidArgumentException(sprintf('A "tags" entry must be an array for service "%s".', $id));
                }

                if (!isset($tag['name'])) {
                    throw new InvalidArgumentException(sprintf('A "tags" entry is missing a "name" key for service "%s".', $id));
                }

                $name = $tag['name'];
                unset($tag['name']);

                foreach ($tag as $attribute => $value) {
                    if (!is_scalar($value) && null !== $value) {
                        throw new InvalidArgumentException(sprintf('A "tags" attribute must be of a scalar-type for service "%s", tag "%s", attribute "%s".', $id, $name, $attribute));
                    }
                }

                $definition->addTag($name, $tag);
            }
        }

        if (isset($service['decorates'])) {
            $renameId = isset($service['decoration_inner_name']) ? $service['decoration_inner_name'] : null;
            $definition->setDecoratedService($service['decorates'], $renameId);
        }

        $this->container->setDefinition($id, $definition);
    }

    /**
     * Validates a config array.
     *
     * @param array $content
     *
     * @throws InvalidArgumentException When service config is not valid
     */
    private function validate(array $content)
    {
        foreach (array_keys($content) as $namespace) {
            if (in_array($namespace, array('imports', 'parameters', 'services'))) {
                continue;
            }

            if (!$this->container->hasExtension($namespace)) {
                $extensionNamespaces = array_filter(array_map(function ($ext) { return $ext->getAlias(); }, $this->container->getExtensions()));
                throw new InvalidArgumentException(sprintf(
                    'There is no extension able to load the configuration for "%s". Looked for namespace "%s", found %s',
                    $namespace,
                    $namespace,
                    $extensionNamespaces ? sprintf('"%s"', implode('", "', $extensionNamespaces)) : 'none'
                ));
            }
        }
    }

    /**
     * Resolves services.
     *
     * @param string|array $value
     *
     * @return array|string|Reference
     */
    private function resolveServices($value)
    {
        if (is_array($value)) {
            $value = array_map(array($this, 'resolveServices'), $value);
        } elseif (is_string($value) &&  0 === strpos($value, '@=')) {
            return new Expression(substr($value, 2));
        } elseif (is_string($value) &&  0 === strpos($value, '@')) {
            if (0 === strpos($value, '@@')) {
                $value = substr($value, 1);
                $invalidBehavior = null;
            } elseif (0 === strpos($value, '@?')) {
                $value = substr($value, 2);
                $invalidBehavior = ContainerInterface::IGNORE_ON_INVALID_REFERENCE;
            } else {
                $value = substr($value, 1);
                $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
            }

            if ('=' === substr($value, -1)) {
                $value = substr($value, 0, -1);
                $strict = false;
            } else {
                $strict = true;
            }

            if (null !== $invalidBehavior) {
                $value = new Reference($value, $invalidBehavior, $strict);
            }
        }

        return $value;
    }

    /**
     * Loads from Extensions
     *
     * @param array $content
     */
    private function loadFromExtensions($content)
    {
        foreach ($content as $namespace => $values) {
            if (in_array($namespace, array('imports', 'parameters', 'services'))) {
                continue;
            }

            if (!is_array($values)) {
                $values = array();
            }

            $this->container->loadFromExtension($namespace, $values);
        }
    }
}
