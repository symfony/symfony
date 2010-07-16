<?php

namespace Symfony\Components\DependencyInjection\Loader;

use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\DependencyInjection\Definition;
use Symfony\Components\DependencyInjection\Reference;
use Symfony\Components\DependencyInjection\ContainerBuilder;
use Symfony\Components\DependencyInjection\Resource\FileResource;
use Symfony\Components\Yaml\Yaml;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * YamlFileLoader loads YAML files service definitions.
 *
 * The YAML format does not support anonymous services (cf. the XML loader).
 *
 * @package    Symfony
 * @subpackage Components_DependencyInjection
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class YamlFileLoader extends FileLoader
{
    /**
     * Loads an array of Yaml files.
     *
     * @param mixed $resource The resource
     */
    public function load($file)
    {
        $path = $this->findFile($file);

        $content = $this->loadFile($path);

        $this->container->addResource(new FileResource($path));

        if (!$content) {
            return;
        }

        // imports
        $this->parseImports($content, $file);

        // extensions
        $this->loadFromExtensions($content);

        // parameters
        if (isset($content['parameters'])) {
            foreach ($content['parameters'] as $key => $value) {
                $this->container->setParameter($key, $this->resolveServices($value));
            }
        }

        // services
        $this->parseDefinitions($content, $file);
    }

    protected function parseImports($content, $file)
    {
        if (!isset($content['imports'])) {
            return;
        }

        foreach ($content['imports'] as $import) {
            $this->parseImport($import, $file);
        }
    }

    protected function parseImport($import, $file)
    {
        $class = null;
        if (isset($import['class']) && $import['class'] !== get_class($this)) {
            $class = $import['class'];
        } else {
            // try to detect loader with the extension
            switch (pathinfo($import['resource'], PATHINFO_EXTENSION)) {
                case 'xml':
                    $class = 'Symfony\\Components\\DependencyInjection\\Loader\\XmlFileLoader';
                    break;
                case 'ini':
                    $class = 'Symfony\\Components\\DependencyInjection\\Loader\\IniFileLoader';
                    break;
            }
        }

        $loader = null === $class ? $this : new $class($this->container, $this->paths);

        $importedFile = $this->getAbsolutePath($import['resource'], dirname($file));

        $loader->load($importedFile);
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
        }

        $definition = new Definition();

        if (isset($service['class'])) {
            $definition->setClass($service['class']);
        }

        if (isset($service['shared'])) {
            $definition->setShared($service['shared']);
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

        if (isset($service['annotations'])) {
            foreach ($service['annotations'] as $annotation) {
                $name = $annotation['name'];
                unset($annotation['name']);

                $definition->addAnnotation($name, $annotation);
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
            if (in_array($key, array('imports', 'parameters', 'services'))) {
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
        } else if (is_string($value) && 0 === strpos($value, '@@')) {
            $value = new Reference(substr($value, 2), ContainerInterface::IGNORE_ON_INVALID_REFERENCE);
        } else if (is_string($value) && 0 === strpos($value, '@')) {
            $value = new Reference(substr($value, 1));
        }

        return $value;
    }

    protected function loadFromExtensions($content)
    {
        foreach ($content as $key => $values) {
            if (in_array($key, array('imports', 'parameters', 'services'))) {
                continue;
            }

            list($namespace, $tag) = explode('.', $key);

            if (!is_array($values)) {
                $values = array();
            }

            $this->container->loadFromExtension($this->container->getExtension($namespace), $tag, $values);
        }
    }
}
