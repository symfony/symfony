<?php

namespace Symfony\Components\DependencyInjection\Loader;

use Symfony\Components\DependencyInjection\Container;
use Symfony\Components\DependencyInjection\Definition;
use Symfony\Components\DependencyInjection\Reference;
use Symfony\Components\DependencyInjection\BuilderConfiguration;
use Symfony\Components\DependencyInjection\FileResource;
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
     * @param mixed                $resource       The resource
     * @param Boolean              $main           Whether this is the main load() call
     * @param BuilderConfiguration $configuration  A BuilderConfiguration instance to use for the configuration
     *
     * @return BuilderConfiguration A BuilderConfiguration instance
     */
    public function load($file, $main = true, BuilderConfiguration $configuration = null)
    {
        $path = $this->findFile($file);

        $content = $this->loadFile($path);

        if (null === $configuration) {
            $configuration = new BuilderConfiguration();
        }

        $configuration->addResource(new FileResource($path));

        if (!$content) {
            return $configuration;
        }

        // imports
        $this->parseImports($configuration, $content, $file);

        // parameters
        if (isset($content['parameters'])) {
            foreach ($content['parameters'] as $key => $value) {
                $configuration->setParameter(strtolower($key), $this->resolveServices($value));
            }
        }

        // services
        $this->parseDefinitions($configuration, $content, $file);

        // extensions
        $this->loadFromExtensions($configuration, $content);

        if ($main) {
            $configuration->mergeExtensionsConfiguration();
        }

        return $configuration;
    }

    protected function parseImports(BuilderConfiguration $configuration, $content, $file)
    {
        if (!isset($content['imports'])) {
            return;
        }

        foreach ($content['imports'] as $import) {
            $this->parseImport($configuration, $import, $file);
        }
    }

    protected function parseImport(BuilderConfiguration $configuration, $import, $file)
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

        $loader = null === $class ? $this : new $class($this->paths);

        $importedFile = $this->getAbsolutePath($import['resource'], dirname($file));

        return $loader->load($importedFile, false, $configuration);
    }

    protected function parseDefinitions(BuilderConfiguration $configuration, $content, $file)
    {
        if (!isset($content['services'])) {
            return;
        }

        foreach ($content['services'] as $id => $service) {
            $this->parseDefinition($configuration, $id, $service, $file);
        }
    }

    protected function parseDefinition(BuilderConfiguration $configuration, $id, $service, $file)
    {
        if (is_string($service) && 0 === strpos($service, '@')) {
            $configuration->setAlias($id, substr($service, 1));

            return;
        }

        $definition = new Definition($service['class']);

        if (isset($service['shared'])) {
            $definition->setShared($service['shared']);
        }

        if (isset($service['constructor'])) {
            $definition->setConstructor($service['constructor']);
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

        $configuration->setDefinition($id, $definition);
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
                if (!static::getExtension($namespace)) {
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
            $value = new Reference(substr($value, 2), Container::IGNORE_ON_INVALID_REFERENCE);
        } else if (is_string($value) && 0 === strpos($value, '@')) {
            $value = new Reference(substr($value, 1));
        }

        return $value;
    }

    protected function loadFromExtensions(BuilderConfiguration $configuration, $content)
    {
        foreach ($content as $key => $values) {
            if (in_array($key, array('imports', 'parameters', 'services'))) {
                continue;
            }

            list($namespace, $tag) = explode('.', $key);

            if (!is_array($values)) {
                $values = array();
            }

            $configuration->loadFromExtension($this->getExtension($namespace), $tag, $values);
        }
    }
}
