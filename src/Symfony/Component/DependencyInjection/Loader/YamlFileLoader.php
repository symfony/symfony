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

use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser as YamlParser;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * YamlFileLoader loads YAML files service definitions.
 *
 * The YAML format does not support anonymous services (cf. the XML loader).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class YamlFileLoader extends FileLoader
{
    private static $keywords = array(
        'alias' => 'alias',
        'parent' => 'parent',
        'class' => 'class',
        'shared' => 'shared',
        'synthetic' => 'synthetic',
        'lazy' => 'lazy',
        'public' => 'public',
        'abstract' => 'abstract',
        'deprecated' => 'deprecated',
        'factory' => 'factory',
        'file' => 'file',
        'arguments' => 'arguments',
        'properties' => 'properties',
        'configurator' => 'configurator',
        'calls' => 'calls',
        'tags' => 'tags',
        'decorates' => 'decorates',
        'decoration_inner_name' => 'decoration_inner_name',
        'decoration_priority' => 'decoration_priority',
        'autowire' => 'autowire',
        'autowiring_types' => 'autowiring_types',
    );

    private $yamlParser;

    /**
     * {@inheritdoc}
     */
    public function load($resource, $type = null)
    {
        $path = $this->locator->locate($resource);

        $content = $this->loadFile($path);

        $this->container->addResource(new FileResource($path));

        // empty file
        if (null === $content) {
            return;
        }

        // imports
        $this->parseImports($content, $path);

        // parameters
        if (isset($content['parameters'])) {
            if (!is_array($content['parameters'])) {
                throw new InvalidArgumentException(sprintf('The "parameters" key should contain an array in %s. Check your YAML syntax.', $resource));
            }

            foreach ($content['parameters'] as $key => $value) {
                $this->container->setParameter($key, $this->resolveServices($value));
            }
        }

        // extensions
        $this->loadFromExtensions($content);

        // services
        $this->parseDefinitions($content, $resource);
    }

    /**
     * {@inheritdoc}
     */
    public function supports($resource, $type = null)
    {
        return is_string($resource) && in_array(pathinfo($resource, PATHINFO_EXTENSION), array('yml', 'yaml'), true);
    }

    /**
     * Parses all imports.
     *
     * @param array  $content
     * @param string $file
     */
    private function parseImports(array $content, $file)
    {
        if (!isset($content['imports'])) {
            return;
        }

        if (!is_array($content['imports'])) {
            throw new InvalidArgumentException(sprintf('The "imports" key should contain an array in %s. Check your YAML syntax.', $file));
        }

        $defaultDirectory = dirname($file);
        foreach ($content['imports'] as $import) {
            if (!is_array($import)) {
                throw new InvalidArgumentException(sprintf('The values in the "imports" key should be arrays in %s. Check your YAML syntax.', $file));
            }

            $this->setCurrentDir($defaultDirectory);
            $this->import($import['resource'], null, isset($import['ignore_errors']) ? (bool) $import['ignore_errors'] : false, $file);
        }
    }

    /**
     * Parses definitions.
     *
     * @param array  $content
     * @param string $file
     */
    private function parseDefinitions(array $content, $file)
    {
        if (!isset($content['services'])) {
            return;
        }

        if (!is_array($content['services'])) {
            throw new InvalidArgumentException(sprintf('The "services" key should contain an array in %s. Check your YAML syntax.', $file));
        }

        foreach ($content['services'] as $id => $service) {
            $this->parseDefinition($id, $service, $file);
        }
    }

    /**
     * Parses a definition.
     *
     * @param string       $id
     * @param array|string $service
     * @param string       $file
     *
     * @throws InvalidArgumentException When tags are invalid
     */
    private function parseDefinition($id, $service, $file)
    {
        if (is_string($service) && 0 === strpos($service, '@')) {
            $this->container->setAlias($id, substr($service, 1));

            return;
        }

        if (!is_array($service)) {
            throw new InvalidArgumentException(sprintf('A service definition must be an array or a string starting with "@" but %s found for service "%s" in %s. Check your YAML syntax.', gettype($service), $id, $file));
        }

        static::checkDefinition($id, $service, $file);

        if (isset($service['alias'])) {
            $public = !array_key_exists('public', $service) || (bool) $service['public'];
            $this->container->setAlias($id, new Alias($service['alias'], $public));

            foreach ($service as $key => $value) {
                if (!in_array($key, array('alias', 'public'))) {
                    @trigger_error(sprintf('The configuration key "%s" is unsupported for the service "%s" which is defined as an alias in "%s". Allowed configuration keys for service aliases are "alias" and "public". The YamlFileLoader will raise an exception in Symfony 4.0, instead of silently ignoring unsupported attributes.', $key, $id, $file), E_USER_DEPRECATED);
                }
            }

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

        if (isset($service['shared'])) {
            $definition->setShared($service['shared']);
        }

        if (isset($service['synthetic'])) {
            $definition->setSynthetic($service['synthetic']);
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

        if (array_key_exists('deprecated', $service)) {
            $definition->setDeprecated(true, $service['deprecated']);
        }

        if (isset($service['factory'])) {
            $definition->setFactory($this->parseCallable($service['factory'], 'factory', $id, $file));
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
            $definition->setConfigurator($this->parseCallable($service['configurator'], 'configurator', $id, $file));
        }

        if (isset($service['calls'])) {
            if (!is_array($service['calls'])) {
                throw new InvalidArgumentException(sprintf('Parameter "calls" must be an array for service "%s" in %s. Check your YAML syntax.', $id, $file));
            }

            foreach ($service['calls'] as $call) {
                if (isset($call['method'])) {
                    $method = $call['method'];
                    $args = isset($call['arguments']) ? $this->resolveServices($call['arguments']) : array();
                } else {
                    $method = $call[0];
                    $args = isset($call[1]) ? $this->resolveServices($call[1]) : array();
                }

                $definition->addMethodCall($method, $args);
            }
        }

        if (isset($service['tags'])) {
            if (!is_array($service['tags'])) {
                throw new InvalidArgumentException(sprintf('Parameter "tags" must be an array for service "%s" in %s. Check your YAML syntax.', $id, $file));
            }

            foreach ($service['tags'] as $tag) {
                if (!is_array($tag)) {
                    throw new InvalidArgumentException(sprintf('A "tags" entry must be an array for service "%s" in %s. Check your YAML syntax.', $id, $file));
                }

                if (!isset($tag['name'])) {
                    throw new InvalidArgumentException(sprintf('A "tags" entry is missing a "name" key for service "%s" in %s.', $id, $file));
                }

                if (!is_string($tag['name']) || '' === $tag['name']) {
                    throw new InvalidArgumentException(sprintf('The tag name for service "%s" in %s must be a non-empty string.', $id, $file));
                }

                $name = $tag['name'];
                unset($tag['name']);

                foreach ($tag as $attribute => $value) {
                    if (!is_scalar($value) && null !== $value) {
                        throw new InvalidArgumentException(sprintf('A "tags" attribute must be of a scalar-type for service "%s", tag "%s", attribute "%s" in %s. Check your YAML syntax.', $id, $name, $attribute, $file));
                    }
                }

                $definition->addTag($name, $tag);
            }
        }

        if (isset($service['decorates'])) {
            if ('' !== $service['decorates'] && '@' === $service['decorates'][0]) {
                throw new InvalidArgumentException(sprintf('The value of the "decorates" option for the "%s" service must be the id of the service without the "@" prefix (replace "%s" with "%s").', $id, $service['decorates'], substr($service['decorates'], 1)));
            }

            $renameId = isset($service['decoration_inner_name']) ? $service['decoration_inner_name'] : null;
            $priority = isset($service['decoration_priority']) ? $service['decoration_priority'] : 0;
            $definition->setDecoratedService($service['decorates'], $renameId, $priority);
        }

        if (isset($service['autowire'])) {
            $definition->setAutowired($service['autowire']);
        }

        if (isset($service['autowiring_types'])) {
            if (is_string($service['autowiring_types'])) {
                $definition->addAutowiringType($service['autowiring_types']);
            } else {
                if (!is_array($service['autowiring_types'])) {
                    throw new InvalidArgumentException(sprintf('Parameter "autowiring_types" must be a string or an array for service "%s" in %s. Check your YAML syntax.', $id, $file));
                }

                foreach ($service['autowiring_types'] as $autowiringType) {
                    if (!is_string($autowiringType)) {
                        throw new InvalidArgumentException(sprintf('A "autowiring_types" attribute must be of type string for service "%s" in %s. Check your YAML syntax.', $id, $file));
                    }

                    $definition->addAutowiringType($autowiringType);
                }
            }
        }

        $this->container->setDefinition($id, $definition);
    }

    /**
     * Parses a callable.
     *
     * @param string|array $callable  A callable
     * @param string       $parameter A parameter (e.g. 'factory' or 'configurator')
     * @param string       $id        A service identifier
     * @param string       $file      A parsed file
     *
     * @throws InvalidArgumentException When errors are occuried
     *
     * @return string|array A parsed callable
     */
    private function parseCallable($callable, $parameter, $id, $file)
    {
        if (is_string($callable)) {
            if ('' !== $callable && '@' === $callable[0]) {
                throw new InvalidArgumentException(sprintf('The value of the "%s" option for the "%s" service must be the id of the service without the "@" prefix (replace "%s" with "%s").', $parameter, $id, $callable, substr($callable, 1)));
            }

            if (false !== strpos($callable, ':') && false === strpos($callable, '::')) {
                $parts = explode(':', $callable);

                return array($this->resolveServices('@'.$parts[0]), $parts[1]);
            }

            return $callable;
        }

        if (is_array($callable)) {
            if (isset($callable[0]) && isset($callable[1])) {
                return array($this->resolveServices($callable[0]), $callable[1]);
            }

            throw new InvalidArgumentException(sprintf('Parameter "%s" must contain an array with two elements for service "%s" in %s. Check your YAML syntax.', $parameter, $id, $file));
        }

        throw new InvalidArgumentException(sprintf('Parameter "%s" must be a string or an array for service "%s" in %s. Check your YAML syntax.', $parameter, $id, $file));
    }

    /**
     * Loads a YAML file.
     *
     * @param string $file
     *
     * @return array The file content
     *
     * @throws InvalidArgumentException when the given file is not a local file or when it does not exist
     */
    protected function loadFile($file)
    {
        if (!class_exists('Symfony\Component\Yaml\Parser')) {
            throw new RuntimeException('Unable to load YAML config files as the Symfony Yaml Component is not installed.');
        }

        if (!stream_is_local($file)) {
            throw new InvalidArgumentException(sprintf('This is not a local file "%s".', $file));
        }

        if (!file_exists($file)) {
            throw new InvalidArgumentException(sprintf('The service file "%s" is not valid.', $file));
        }

        if (null === $this->yamlParser) {
            $this->yamlParser = new YamlParser();
        }

        try {
            $configuration = $this->yamlParser->parse(file_get_contents($file), Yaml::PARSE_CONSTANT);
        } catch (ParseException $e) {
            throw new InvalidArgumentException(sprintf('The file "%s" does not contain valid YAML.', $file), 0, $e);
        }

        return $this->validate($configuration, $file);
    }

    /**
     * Validates a YAML file.
     *
     * @param mixed  $content
     * @param string $file
     *
     * @return array
     *
     * @throws InvalidArgumentException When service file is not valid
     */
    private function validate($content, $file)
    {
        if (null === $content) {
            return $content;
        }

        if (!is_array($content)) {
            throw new InvalidArgumentException(sprintf('The service file "%s" is not valid. It should contain an array. Check your YAML syntax.', $file));
        }

        foreach ($content as $namespace => $data) {
            if (in_array($namespace, array('imports', 'parameters', 'services'))) {
                continue;
            }

            if (!$this->container->hasExtension($namespace)) {
                $extensionNamespaces = array_filter(array_map(function ($ext) { return $ext->getAlias(); }, $this->container->getExtensions()));
                throw new InvalidArgumentException(sprintf(
                    'There is no extension able to load the configuration for "%s" (in %s). Looked for namespace "%s", found %s',
                    $namespace,
                    $file,
                    $namespace,
                    $extensionNamespaces ? sprintf('"%s"', implode('", "', $extensionNamespaces)) : 'none'
                ));
            }
        }

        return $content;
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
        } elseif (is_string($value) && 0 === strpos($value, '@=')) {
            return new Expression(substr($value, 2));
        } elseif (is_string($value) && 0 === strpos($value, '@')) {
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
            }

            if (null !== $invalidBehavior) {
                $value = new Reference($value, $invalidBehavior);
            }
        }

        return $value;
    }

    /**
     * Loads from Extensions.
     *
     * @param array $content
     */
    private function loadFromExtensions(array $content)
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

    /**
     * Checks the keywords used to define a service.
     *
     * @param string $id         The service name
     * @param array  $definition The service definition to check
     * @param string $file       The loaded YAML file
     */
    private static function checkDefinition($id, array $definition, $file)
    {
        foreach ($definition as $key => $value) {
            if (!isset(static::$keywords[$key])) {
                @trigger_error(sprintf('The configuration key "%s" is unsupported for service definition "%s" in "%s". Allowed configuration keys are "%s". The YamlFileLoader object will raise an exception instead in Symfony 4.0 when detecting an unsupported service configuration key.', $key, $id, $file, implode('", "', static::$keywords)), E_USER_DEPRECATED);
                // @deprecated Uncomment the following statement in Symfony 4.0
                // and also update the corresponding unit test to make it expect
                // an InvalidArgumentException exception.
                //throw new InvalidArgumentException(sprintf('The configuration key "%s" is unsupported for service definition "%s" in "%s". Allowed configuration keys are "%s".', $key, $id, $file, implode('", "', static::$keywords)));
            }
        }
    }
}
