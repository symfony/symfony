<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader;

use Symfony\Component\DependencyInjection\DefinitionDecorator;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\InterfaceInjector;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Resource\FileResource;
use Symfony\Component\Yaml\Yaml;

/**
 * YamlFileLoader loads YAML files service definitions.
 *
 * The YAML format does not support anonymous services (cf. the XML loader).
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class YamlFileLoader extends FileLoader
{
    /**
     * Loads a Yaml file.
     *
     * @param mixed $resource The resource
     */
    public function load($file)
    {
        $path = $this->findFile($file);

        $content = $this->loadFile($path);

        $this->container->addResource(new FileResource($path));

        // empty file
        if (null === $content) {
            return;
        }

        // imports
        $this->parseImports($content, $file);

        // parameters
        if (isset($content['parameters'])) {
            foreach ($content['parameters'] as $key => $value) {
                $this->container->setParameter($key, $this->resolveServices($value));
            }
        }

        // extensions
        $this->loadFromExtensions($content);

        // interface injectors
        $this->parseInterfaceInjectors($content, $file);

        // services
        $this->parseDefinitions($content, $file);
    }

    /**
     * Returns true if this class supports the given resource.
     *
     * @param  mixed $resource A resource
     *
     * @return Boolean true if this class supports the given resource, false otherwise
     */
    public function supports($resource)
    {
        return is_string($resource) && 'yml' === pathinfo($resource, PATHINFO_EXTENSION);
    }

    protected function parseImports($content, $file)
    {
        if (!isset($content['imports'])) {
            return;
        }

        foreach ($content['imports'] as $import) {
            $this->currentDir = dirname($file);
            $this->import($import['resource'], isset($import['ignore_errors']) ? (Boolean) $import['ignore_errors'] : false);
        }
    }

    protected function parseInterfaceInjectors($content, $file)
    {
        if (!isset($content['interfaces'])) {
            return;
        }

        foreach ($content['interfaces'] as $class => $interface) {
            $this->parseInterfaceInjector($class, $interface, $file);
        }
    }

    protected function parseInterfaceInjector($class, $interface, $file)
    {
        $injector = new InterfaceInjector($class);
        if (isset($interface['calls'])) {
            foreach ($interface['calls'] as $call) {
                $injector->addMethodCall($call[0], $this->resolveServices($call[1]));
            }
        }
        $this->container->addInterfaceInjector($injector);
    }

    protected function parseDefinitions($content, $file)
    {
        if (!isset($content['services'])) {
            return;
        }

        foreach ($content['services'] as $id => $service) {
            $this->parseDefinition($id, $service, $file);
        }
    }

    protected function parseDefinition($id, $service, $file)
    {
        if (is_string($service) && 0 === strpos($service, '@')) {
            $this->container->setAlias($id, substr($service, 1));

            return;
        } else if (isset($service['alias'])) {
            $public = !array_key_exists('public', $service) || (Boolean) $service['public'];
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

        if (isset($service['configurator'])) {
            if (is_string($service['configurator'])) {
                $definition->setConfigurator($service['configurator']);
            } else {
                $definition->setConfigurator(array($this->resolveServices($service['configurator'][0]), $service['configurator'][1]));
            }
        }

        if (isset($service['calls'])) {
            foreach ($service['calls'] as $call) {
                $definition->addMethodCall($call[0], $this->resolveServices($call[1]));
            }
        }

        if (isset($service['tags'])) {
            foreach ($service['tags'] as $tag) {
                $name = $tag['name'];
                unset($tag['name']);

                $definition->addTag($name, $tag);
            }
        }

        $this->container->setDefinition($id, $definition);
    }

    protected function loadFile($file)
    {
        return $this->validate(Yaml::load($file), $file);
    }

    /**
     * @throws \InvalidArgumentException When service file is not valid
     */
    protected function validate($content, $file)
    {
        if (null === $content) {
            return $content;
        }

        if (!is_array($content)) {
            throw new \InvalidArgumentException(sprintf('The service file "%s" is not valid.', $file));
        }

        foreach (array_keys($content) as $key) {
            if (in_array($key, array('imports', 'parameters', 'services', 'interfaces'))) {
                continue;
            }

            // can it be handled by an extension?
            if (false !== strpos($key, '.')) {
                list($namespace, $tag) = explode('.', $key);
                if (!$this->container->hasExtension($namespace)) {
                    throw new \InvalidArgumentException(sprintf('There is no extension able to load the configuration for "%s" (in %s).', $key, $file));
                }

                continue;
            }

            throw new \InvalidArgumentException(sprintf('The "%s" tag is not valid (in %s).', $key, $file));
        }

        return $content;
    }

    protected function resolveServices($value)
    {
        if (is_array($value)) {
            $value = array_map(array($this, 'resolveServices'), $value);
        } else if (is_string($value) &&  0 === strpos($value, '@')) {
            if (0 === strpos($value, '@?')) {
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

            $value = new Reference($value, $invalidBehavior, $strict);
        }

        return $value;
    }

    protected function loadFromExtensions($content)
    {
        foreach ($content as $key => $values) {
            if (in_array($key, array('imports', 'parameters', 'services', 'interfaces'))) {
                continue;
            }

            list($namespace, $tag) = explode('.', $key);

            if (!is_array($values)) {
                $values = array();
            }

            $this->container->loadFromExtension($namespace, $tag, $values);
        }
    }
}
