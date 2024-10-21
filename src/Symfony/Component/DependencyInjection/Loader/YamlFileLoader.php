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

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser as YamlParser;
use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Yaml;

/**
 * YamlFileLoader loads YAML files service definitions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class YamlFileLoader extends FileLoader
{
    private const SERVICE_KEYWORDS = [
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
        'decoration_on_invalid' => 'decoration_on_invalid',
        'autowire' => 'autowire',
        'autoconfigure' => 'autoconfigure',
        'bind' => 'bind',
    ];

    private const PROTOTYPE_KEYWORDS = [
        'resource' => 'resource',
        'namespace' => 'namespace',
        'exclude' => 'exclude',
        'parent' => 'parent',
        'shared' => 'shared',
        'lazy' => 'lazy',
        'public' => 'public',
        'abstract' => 'abstract',
        'deprecated' => 'deprecated',
        'factory' => 'factory',
        'arguments' => 'arguments',
        'properties' => 'properties',
        'configurator' => 'configurator',
        'calls' => 'calls',
        'tags' => 'tags',
        'autowire' => 'autowire',
        'autoconfigure' => 'autoconfigure',
        'bind' => 'bind',
    ];

    private const INSTANCEOF_KEYWORDS = [
        'shared' => 'shared',
        'lazy' => 'lazy',
        'public' => 'public',
        'properties' => 'properties',
        'configurator' => 'configurator',
        'calls' => 'calls',
        'tags' => 'tags',
        'autowire' => 'autowire',
        'bind' => 'bind',
    ];

    private const DEFAULTS_KEYWORDS = [
        'public' => 'public',
        'tags' => 'tags',
        'autowire' => 'autowire',
        'autoconfigure' => 'autoconfigure',
        'bind' => 'bind',
    ];

    private $yamlParser;

    private $anonymousServicesCount;
    private $anonymousServicesSuffix;

    protected $autoRegisterAliasesForSinglyImplementedInterfaces = false;

    /**
     * {@inheritdoc}
     */
    public function load($resource, ?string $type = null)
    {
        $path = $this->locator->locate($resource);

        $content = $this->loadFile($path);

        $this->container->fileExists($path);

        // empty file
        if (null === $content) {
            return null;
        }

        $this->loadContent($content, $path);

        // per-env configuration
        if ($this->env && isset($content['when@'.$this->env])) {
            if (!\is_array($content['when@'.$this->env])) {
                throw new InvalidArgumentException(sprintf('The "when@%s" key should contain an array in "%s". Check your YAML syntax.', $this->env, $path));
            }

            $env = $this->env;
            $this->env = null;
            try {
                $this->loadContent($content['when@'.$env], $path);
            } finally {
                $this->env = $env;
            }
        }

        return null;
    }

    private function loadContent(array $content, string $path)
    {
        // imports
        $this->parseImports($content, $path);

        // parameters
        if (isset($content['parameters'])) {
            if (!\is_array($content['parameters'])) {
                throw new InvalidArgumentException(sprintf('The "parameters" key should contain an array in "%s". Check your YAML syntax.', $path));
            }

            foreach ($content['parameters'] as $key => $value) {
                $this->container->setParameter($key, $this->resolveServices($value, $path, true));
            }
        }

        // extensions
        $this->loadFromExtensions($content);

        // services
        $this->anonymousServicesCount = 0;
        $this->anonymousServicesSuffix = '~'.ContainerBuilder::hash($path);
        $this->setCurrentDir(\dirname($path));
        try {
            $this->parseDefinitions($content, $path);
        } finally {
            $this->instanceof = [];
            $this->registerAliasesForSinglyImplementedInterfaces();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports($resource, ?string $type = null)
    {
        if (!\is_string($resource)) {
            return false;
        }

        if (null === $type && \in_array(pathinfo($resource, \PATHINFO_EXTENSION), ['yaml', 'yml'], true)) {
            return true;
        }

        return \in_array($type, ['yaml', 'yml'], true);
    }

    private function parseImports(array $content, string $file)
    {
        if (!isset($content['imports'])) {
            return;
        }

        if (!\is_array($content['imports'])) {
            throw new InvalidArgumentException(sprintf('The "imports" key should contain an array in "%s". Check your YAML syntax.', $file));
        }

        $defaultDirectory = \dirname($file);
        foreach ($content['imports'] as $import) {
            if (!\is_array($import)) {
                $import = ['resource' => $import];
            }
            if (!isset($import['resource'])) {
                throw new InvalidArgumentException(sprintf('An import should provide a resource in "%s". Check your YAML syntax.', $file));
            }

            $this->setCurrentDir($defaultDirectory);
            $this->import($import['resource'], $import['type'] ?? null, $import['ignore_errors'] ?? false, $file);
        }
    }

    private function parseDefinitions(array $content, string $file, bool $trackBindings = true)
    {
        if (!isset($content['services'])) {
            return;
        }

        if (!\is_array($content['services'])) {
            throw new InvalidArgumentException(sprintf('The "services" key should contain an array in "%s". Check your YAML syntax.', $file));
        }

        if (\array_key_exists('_instanceof', $content['services'])) {
            $instanceof = $content['services']['_instanceof'];
            unset($content['services']['_instanceof']);

            if (!\is_array($instanceof)) {
                throw new InvalidArgumentException(sprintf('Service "_instanceof" key must be an array, "%s" given in "%s".', get_debug_type($instanceof), $file));
            }
            $this->instanceof = [];
            $this->isLoadingInstanceof = true;
            foreach ($instanceof as $id => $service) {
                if (!$service || !\is_array($service)) {
                    throw new InvalidArgumentException(sprintf('Type definition "%s" must be a non-empty array within "_instanceof" in "%s". Check your YAML syntax.', $id, $file));
                }
                if (\is_string($service) && str_starts_with($service, '@')) {
                    throw new InvalidArgumentException(sprintf('Type definition "%s" cannot be an alias within "_instanceof" in "%s". Check your YAML syntax.', $id, $file));
                }
                $this->parseDefinition($id, $service, $file, [], false, $trackBindings);
            }
        }

        $this->isLoadingInstanceof = false;
        $defaults = $this->parseDefaults($content, $file);
        foreach ($content['services'] as $id => $service) {
            $this->parseDefinition($id, $service, $file, $defaults, false, $trackBindings);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function parseDefaults(array &$content, string $file): array
    {
        if (!\array_key_exists('_defaults', $content['services'])) {
            return [];
        }
        $defaults = $content['services']['_defaults'];
        unset($content['services']['_defaults']);

        if (!\is_array($defaults)) {
            throw new InvalidArgumentException(sprintf('Service "_defaults" key must be an array, "%s" given in "%s".', get_debug_type($defaults), $file));
        }

        foreach ($defaults as $key => $default) {
            if (!isset(self::DEFAULTS_KEYWORDS[$key])) {
                throw new InvalidArgumentException(sprintf('The configuration key "%s" cannot be used to define a default value in "%s". Allowed keys are "%s".', $key, $file, implode('", "', self::DEFAULTS_KEYWORDS)));
            }
        }

        if (isset($defaults['tags'])) {
            if (!\is_array($tags = $defaults['tags'])) {
                throw new InvalidArgumentException(sprintf('Parameter "tags" in "_defaults" must be an array in "%s". Check your YAML syntax.', $file));
            }

            foreach ($tags as $tag) {
                if (!\is_array($tag)) {
                    $tag = ['name' => $tag];
                }

                if (1 === \count($tag) && \is_array(current($tag))) {
                    $name = key($tag);
                    $tag = current($tag);
                } else {
                    if (!isset($tag['name'])) {
                        throw new InvalidArgumentException(sprintf('A "tags" entry in "_defaults" is missing a "name" key in "%s".', $file));
                    }
                    $name = $tag['name'];
                    unset($tag['name']);
                }

                if (!\is_string($name) || '' === $name) {
                    throw new InvalidArgumentException(sprintf('The tag name in "_defaults" must be a non-empty string in "%s".', $file));
                }

                foreach ($tag as $attribute => $value) {
                    if (!\is_scalar($value) && null !== $value) {
                        throw new InvalidArgumentException(sprintf('Tag "%s", attribute "%s" in "_defaults" must be of a scalar-type in "%s". Check your YAML syntax.', $name, $attribute, $file));
                    }
                }
            }
        }

        if (isset($defaults['bind'])) {
            if (!\is_array($defaults['bind'])) {
                throw new InvalidArgumentException(sprintf('Parameter "bind" in "_defaults" must be an array in "%s". Check your YAML syntax.', $file));
            }

            foreach ($this->resolveServices($defaults['bind'], $file) as $argument => $value) {
                $defaults['bind'][$argument] = new BoundArgument($value, true, BoundArgument::DEFAULTS_BINDING, $file);
            }
        }

        return $defaults;
    }

    private function isUsingShortSyntax(array $service): bool
    {
        foreach ($service as $key => $value) {
            if (\is_string($key) && ('' === $key || ('$' !== $key[0] && !str_contains($key, '\\')))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parses a definition.
     *
     * @param array|string|null $service
     *
     * @throws InvalidArgumentException When tags are invalid
     */
    private function parseDefinition(string $id, $service, string $file, array $defaults, bool $return = false, bool $trackBindings = true)
    {
        if (preg_match('/^_[a-zA-Z0-9_]*$/', $id)) {
            throw new InvalidArgumentException(sprintf('Service names that start with an underscore are reserved. Rename the "%s" service or define it in XML instead.', $id));
        }

        if (\is_string($service) && str_starts_with($service, '@')) {
            $alias = new Alias(substr($service, 1));

            if (isset($defaults['public'])) {
                $alias->setPublic($defaults['public']);
            }

            return $return ? $alias : $this->container->setAlias($id, $alias);
        }

        if (\is_array($service) && $this->isUsingShortSyntax($service)) {
            $service = ['arguments' => $service];
        }

        if (null === $service) {
            $service = [];
        }

        if (!\is_array($service)) {
            throw new InvalidArgumentException(sprintf('A service definition must be an array or a string starting with "@" but "%s" found for service "%s" in "%s". Check your YAML syntax.', get_debug_type($service), $id, $file));
        }

        if (isset($service['stack'])) {
            if (!\is_array($service['stack'])) {
                throw new InvalidArgumentException(sprintf('A stack must be an array of definitions, "%s" given for service "%s" in "%s". Check your YAML syntax.', get_debug_type($service), $id, $file));
            }

            $stack = [];

            foreach ($service['stack'] as $k => $frame) {
                if (\is_array($frame) && 1 === \count($frame) && !isset(self::SERVICE_KEYWORDS[key($frame)])) {
                    $frame = [
                        'class' => key($frame),
                        'arguments' => current($frame),
                    ];
                }

                if (\is_array($frame) && isset($frame['stack'])) {
                    throw new InvalidArgumentException(sprintf('Service stack "%s" cannot contain another stack in "%s".', $id, $file));
                }

                $definition = $this->parseDefinition($id.'" at index "'.$k, $frame, $file, $defaults, true);

                if ($definition instanceof Definition) {
                    $definition->setInstanceofConditionals($this->instanceof);
                }

                $stack[$k] = $definition;
            }

            if ($diff = array_diff(array_keys($service), ['stack', 'public', 'deprecated'])) {
                throw new InvalidArgumentException(sprintf('Invalid attribute "%s"; supported ones are "public" and "deprecated" for service "%s" in "%s". Check your YAML syntax.', implode('", "', $diff), $id, $file));
            }

            $service = [
                'parent' => '',
                'arguments' => $stack,
                'tags' => ['container.stack'],
                'public' => $service['public'] ?? null,
                'deprecated' => $service['deprecated'] ?? null,
            ];
        }

        $definition = isset($service[0]) && $service[0] instanceof Definition ? array_shift($service) : null;
        $return = null === $definition ? $return : true;

        $this->checkDefinition($id, $service, $file);

        if (isset($service['alias'])) {
            $alias = new Alias($service['alias']);

            if (isset($service['public'])) {
                $alias->setPublic($service['public']);
            } elseif (isset($defaults['public'])) {
                $alias->setPublic($defaults['public']);
            }

            foreach ($service as $key => $value) {
                if (!\in_array($key, ['alias', 'public', 'deprecated'])) {
                    throw new InvalidArgumentException(sprintf('The configuration key "%s" is unsupported for the service "%s" which is defined as an alias in "%s". Allowed configuration keys for service aliases are "alias", "public" and "deprecated".', $key, $id, $file));
                }

                if ('deprecated' === $key) {
                    $deprecation = \is_array($value) ? $value : ['message' => $value];

                    if (!isset($deprecation['package'])) {
                        trigger_deprecation('symfony/dependency-injection', '5.1', 'Not setting the attribute "package" of the "deprecated" option in "%s" is deprecated.', $file);
                    }

                    if (!isset($deprecation['version'])) {
                        trigger_deprecation('symfony/dependency-injection', '5.1', 'Not setting the attribute "version" of the "deprecated" option in "%s" is deprecated.', $file);
                    }

                    $alias->setDeprecated($deprecation['package'] ?? '', $deprecation['version'] ?? '', $deprecation['message'] ?? '');
                }
            }

            return $return ? $alias : $this->container->setAlias($id, $alias);
        }

        $changes = [];
        if (null !== $definition) {
            $changes = $definition->getChanges();
        } elseif ($this->isLoadingInstanceof) {
            $definition = new ChildDefinition('');
        } elseif (isset($service['parent'])) {
            if ('' !== $service['parent'] && '@' === $service['parent'][0]) {
                throw new InvalidArgumentException(sprintf('The value of the "parent" option for the "%s" service must be the id of the service without the "@" prefix (replace "%s" with "%s").', $id, $service['parent'], substr($service['parent'], 1)));
            }

            $definition = new ChildDefinition($service['parent']);
        } else {
            $definition = new Definition();
        }

        if (isset($defaults['public'])) {
            $definition->setPublic($defaults['public']);
        }
        if (isset($defaults['autowire'])) {
            $definition->setAutowired($defaults['autowire']);
        }
        if (isset($defaults['autoconfigure'])) {
            $definition->setAutoconfigured($defaults['autoconfigure']);
        }

        $definition->setChanges($changes);

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
            $definition->setLazy((bool) $service['lazy']);
            if (\is_string($service['lazy'])) {
                $definition->addTag('proxy', ['interface' => $service['lazy']]);
            }
        }

        if (isset($service['public'])) {
            $definition->setPublic($service['public']);
        }

        if (isset($service['abstract'])) {
            $definition->setAbstract($service['abstract']);
        }

        if (isset($service['deprecated'])) {
            $deprecation = \is_array($service['deprecated']) ? $service['deprecated'] : ['message' => $service['deprecated']];

            if (!isset($deprecation['package'])) {
                trigger_deprecation('symfony/dependency-injection', '5.1', 'Not setting the attribute "package" of the "deprecated" option in "%s" is deprecated.', $file);
            }

            if (!isset($deprecation['version'])) {
                trigger_deprecation('symfony/dependency-injection', '5.1', 'Not setting the attribute "version" of the "deprecated" option in "%s" is deprecated.', $file);
            }

            $definition->setDeprecated($deprecation['package'] ?? '', $deprecation['version'] ?? '', $deprecation['message'] ?? '');
        }

        if (isset($service['factory'])) {
            $definition->setFactory($this->parseCallable($service['factory'], 'factory', $id, $file));
        }

        if (isset($service['file'])) {
            $definition->setFile($service['file']);
        }

        if (isset($service['arguments'])) {
            $definition->setArguments($this->resolveServices($service['arguments'], $file));
        }

        if (isset($service['properties'])) {
            $definition->setProperties($this->resolveServices($service['properties'], $file));
        }

        if (isset($service['configurator'])) {
            $definition->setConfigurator($this->parseCallable($service['configurator'], 'configurator', $id, $file));
        }

        if (isset($service['calls'])) {
            if (!\is_array($service['calls'])) {
                throw new InvalidArgumentException(sprintf('Parameter "calls" must be an array for service "%s" in "%s". Check your YAML syntax.', $id, $file));
            }

            foreach ($service['calls'] as $k => $call) {
                if (!\is_array($call) && (!\is_string($k) || !$call instanceof TaggedValue)) {
                    throw new InvalidArgumentException(sprintf('Invalid method call for service "%s": expected map or array, "%s" given in "%s".', $id, $call instanceof TaggedValue ? '!'.$call->getTag() : get_debug_type($call), $file));
                }

                if (\is_string($k)) {
                    throw new InvalidArgumentException(sprintf('Invalid method call for service "%s", did you forget a leading dash before "%s: ..." in "%s"?', $id, $k, $file));
                }

                if (isset($call['method']) && \is_string($call['method'])) {
                    $method = $call['method'];
                    $args = $call['arguments'] ?? [];
                    $returnsClone = $call['returns_clone'] ?? false;
                } else {
                    if (1 === \count($call) && \is_string(key($call))) {
                        $method = key($call);
                        $args = $call[$method];

                        if ($args instanceof TaggedValue) {
                            if ('returns_clone' !== $args->getTag()) {
                                throw new InvalidArgumentException(sprintf('Unsupported tag "!%s", did you mean "!returns_clone" for service "%s" in "%s"?', $args->getTag(), $id, $file));
                            }

                            $returnsClone = true;
                            $args = $args->getValue();
                        } else {
                            $returnsClone = false;
                        }
                    } elseif (empty($call[0])) {
                        throw new InvalidArgumentException(sprintf('Invalid call for service "%s": the method must be defined as the first index of an array or as the only key of a map in "%s".', $id, $file));
                    } else {
                        $method = $call[0];
                        $args = $call[1] ?? [];
                        $returnsClone = $call[2] ?? false;
                    }
                }

                if (!\is_array($args)) {
                    throw new InvalidArgumentException(sprintf('The second parameter for function call "%s" must be an array of its arguments for service "%s" in "%s". Check your YAML syntax.', $method, $id, $file));
                }

                $args = $this->resolveServices($args, $file);
                $definition->addMethodCall($method, $args, $returnsClone);
            }
        }

        $tags = $service['tags'] ?? [];
        if (!\is_array($tags)) {
            throw new InvalidArgumentException(sprintf('Parameter "tags" must be an array for service "%s" in "%s". Check your YAML syntax.', $id, $file));
        }

        if (isset($defaults['tags'])) {
            $tags = array_merge($tags, $defaults['tags']);
        }

        foreach ($tags as $tag) {
            if (!\is_array($tag)) {
                $tag = ['name' => $tag];
            }

            if (1 === \count($tag) && \is_array(current($tag))) {
                $name = key($tag);
                $tag = current($tag);
            } else {
                if (!isset($tag['name'])) {
                    throw new InvalidArgumentException(sprintf('A "tags" entry is missing a "name" key for service "%s" in "%s".', $id, $file));
                }
                $name = $tag['name'];
                unset($tag['name']);
            }

            if (!\is_string($name) || '' === $name) {
                throw new InvalidArgumentException(sprintf('The tag name for service "%s" in "%s" must be a non-empty string.', $id, $file));
            }

            foreach ($tag as $attribute => $value) {
                if (!\is_scalar($value) && null !== $value) {
                    throw new InvalidArgumentException(sprintf('A "tags" attribute must be of a scalar-type for service "%s", tag "%s", attribute "%s" in "%s". Check your YAML syntax.', $id, $name, $attribute, $file));
                }
            }

            $definition->addTag($name, $tag);
        }

        if (null !== $decorates = $service['decorates'] ?? null) {
            if ('' !== $decorates && '@' === $decorates[0]) {
                throw new InvalidArgumentException(sprintf('The value of the "decorates" option for the "%s" service must be the id of the service without the "@" prefix (replace "%s" with "%s").', $id, $service['decorates'], substr($decorates, 1)));
            }

            $decorationOnInvalid = \array_key_exists('decoration_on_invalid', $service) ? $service['decoration_on_invalid'] : 'exception';
            if ('exception' === $decorationOnInvalid) {
                $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
            } elseif ('ignore' === $decorationOnInvalid) {
                $invalidBehavior = ContainerInterface::IGNORE_ON_INVALID_REFERENCE;
            } elseif (null === $decorationOnInvalid) {
                $invalidBehavior = ContainerInterface::NULL_ON_INVALID_REFERENCE;
            } elseif ('null' === $decorationOnInvalid) {
                throw new InvalidArgumentException(sprintf('Invalid value "%s" for attribute "decoration_on_invalid" on service "%s". Did you mean null (without quotes) in "%s"?', $decorationOnInvalid, $id, $file));
            } else {
                throw new InvalidArgumentException(sprintf('Invalid value "%s" for attribute "decoration_on_invalid" on service "%s". Did you mean "exception", "ignore" or null in "%s"?', $decorationOnInvalid, $id, $file));
            }

            $renameId = $service['decoration_inner_name'] ?? null;
            $priority = $service['decoration_priority'] ?? 0;

            $definition->setDecoratedService($decorates, $renameId, $priority, $invalidBehavior);
        }

        if (isset($service['autowire'])) {
            $definition->setAutowired($service['autowire']);
        }

        if (isset($defaults['bind']) || isset($service['bind'])) {
            // deep clone, to avoid multiple process of the same instance in the passes
            $bindings = $definition->getBindings();
            $bindings += isset($defaults['bind']) ? unserialize(serialize($defaults['bind'])) : [];

            if (isset($service['bind'])) {
                if (!\is_array($service['bind'])) {
                    throw new InvalidArgumentException(sprintf('Parameter "bind" must be an array for service "%s" in "%s". Check your YAML syntax.', $id, $file));
                }

                $bindings = array_merge($bindings, $this->resolveServices($service['bind'], $file));
                $bindingType = $this->isLoadingInstanceof ? BoundArgument::INSTANCEOF_BINDING : BoundArgument::SERVICE_BINDING;
                foreach ($bindings as $argument => $value) {
                    if (!$value instanceof BoundArgument) {
                        $bindings[$argument] = new BoundArgument($value, $trackBindings, $bindingType, $file);
                    }
                }
            }

            $definition->setBindings($bindings);
        }

        if (isset($service['autoconfigure'])) {
            $definition->setAutoconfigured($service['autoconfigure']);
        }

        if (\array_key_exists('namespace', $service) && !\array_key_exists('resource', $service)) {
            throw new InvalidArgumentException(sprintf('A "resource" attribute must be set when the "namespace" attribute is set for service "%s" in "%s". Check your YAML syntax.', $id, $file));
        }

        if ($return) {
            if (\array_key_exists('resource', $service)) {
                throw new InvalidArgumentException(sprintf('Invalid "resource" attribute found for service "%s" in "%s". Check your YAML syntax.', $id, $file));
            }

            return $definition;
        }

        if (\array_key_exists('resource', $service)) {
            if (!\is_string($service['resource'])) {
                throw new InvalidArgumentException(sprintf('A "resource" attribute must be of type string for service "%s" in "%s". Check your YAML syntax.', $id, $file));
            }
            $exclude = $service['exclude'] ?? null;
            $namespace = $service['namespace'] ?? $id;
            $this->registerClasses($definition, $namespace, $service['resource'], $exclude);
        } else {
            $this->setDefinition($id, $definition);
        }
    }

    /**
     * Parses a callable.
     *
     * @param string|array $callable A callable reference
     *
     * @return string|array|Reference
     *
     * @throws InvalidArgumentException When errors occur
     */
    private function parseCallable($callable, string $parameter, string $id, string $file)
    {
        if (\is_string($callable)) {
            if ('' !== $callable && '@' === $callable[0]) {
                if (!str_contains($callable, ':')) {
                    return [$this->resolveServices($callable, $file), '__invoke'];
                }

                throw new InvalidArgumentException(sprintf('The value of the "%s" option for the "%s" service must be the id of the service without the "@" prefix (replace "%s" with "%s" in "%s").', $parameter, $id, $callable, substr($callable, 1), $file));
            }

            return $callable;
        }

        if (\is_array($callable)) {
            if (isset($callable[0]) && isset($callable[1])) {
                return [$this->resolveServices($callable[0], $file), $callable[1]];
            }

            if ('factory' === $parameter && isset($callable[1]) && null === $callable[0]) {
                return $callable;
            }

            throw new InvalidArgumentException(sprintf('Parameter "%s" must contain an array with two elements for service "%s" in "%s". Check your YAML syntax.', $parameter, $id, $file));
        }

        throw new InvalidArgumentException(sprintf('Parameter "%s" must be a string or an array for service "%s" in "%s". Check your YAML syntax.', $parameter, $id, $file));
    }

    /**
     * Loads a YAML file.
     *
     * @return array|null
     *
     * @throws InvalidArgumentException when the given file is not a local file or when it does not exist
     */
    protected function loadFile(string $file)
    {
        if (!class_exists(\Symfony\Component\Yaml\Parser::class)) {
            throw new RuntimeException('Unable to load YAML config files as the Symfony Yaml Component is not installed.');
        }

        if (!stream_is_local($file)) {
            throw new InvalidArgumentException(sprintf('This is not a local file "%s".', $file));
        }

        if (!is_file($file)) {
            throw new InvalidArgumentException(sprintf('The file "%s" does not exist.', $file));
        }

        if (null === $this->yamlParser) {
            $this->yamlParser = new YamlParser();
        }

        try {
            $configuration = $this->yamlParser->parseFile($file, Yaml::PARSE_CONSTANT | Yaml::PARSE_CUSTOM_TAGS);
        } catch (ParseException $e) {
            throw new InvalidArgumentException(sprintf('The file "%s" does not contain valid YAML: ', $file).$e->getMessage(), 0, $e);
        }

        return $this->validate($configuration, $file);
    }

    /**
     * Validates a YAML file.
     *
     * @throws InvalidArgumentException When service file is not valid
     */
    private function validate($content, string $file): ?array
    {
        if (null === $content) {
            return $content;
        }

        if (!\is_array($content)) {
            throw new InvalidArgumentException(sprintf('The service file "%s" is not valid. It should contain an array. Check your YAML syntax.', $file));
        }

        foreach ($content as $namespace => $data) {
            if (\in_array($namespace, ['imports', 'parameters', 'services']) || 0 === strpos($namespace, 'when@')) {
                continue;
            }

            if (!$this->container->hasExtension($namespace)) {
                $extensionNamespaces = array_filter(array_map(function (ExtensionInterface $ext) { return $ext->getAlias(); }, $this->container->getExtensions()));
                throw new InvalidArgumentException(sprintf('There is no extension able to load the configuration for "%s" (in "%s"). Looked for namespace "%s", found "%s".', $namespace, $file, $namespace, $extensionNamespaces ? sprintf('"%s"', implode('", "', $extensionNamespaces)) : 'none'));
            }
        }

        return $content;
    }

    /**
     * @return mixed
     */
    private function resolveServices($value, string $file, bool $isParameter = false)
    {
        if ($value instanceof TaggedValue) {
            $argument = $value->getValue();
            if ('iterator' === $value->getTag()) {
                if (!\is_array($argument)) {
                    throw new InvalidArgumentException(sprintf('"!iterator" tag only accepts sequences in "%s".', $file));
                }
                $argument = $this->resolveServices($argument, $file, $isParameter);
                try {
                    return new IteratorArgument($argument);
                } catch (InvalidArgumentException $e) {
                    throw new InvalidArgumentException(sprintf('"!iterator" tag only accepts arrays of "@service" references in "%s".', $file));
                }
            }
            if ('service_closure' === $value->getTag()) {
                $argument = $this->resolveServices($argument, $file, $isParameter);

                if (!$argument instanceof Reference) {
                    throw new InvalidArgumentException(sprintf('"!service_closure" tag only accepts service references in "%s".', $file));
                }

                return new ServiceClosureArgument($argument);
            }
            if ('service_locator' === $value->getTag()) {
                if (!\is_array($argument)) {
                    throw new InvalidArgumentException(sprintf('"!service_locator" tag only accepts maps in "%s".', $file));
                }

                $argument = $this->resolveServices($argument, $file, $isParameter);

                try {
                    return new ServiceLocatorArgument($argument);
                } catch (InvalidArgumentException $e) {
                    throw new InvalidArgumentException(sprintf('"!service_locator" tag only accepts maps of "@service" references in "%s".', $file));
                }
            }
            if (\in_array($value->getTag(), ['tagged', 'tagged_iterator', 'tagged_locator'], true)) {
                $forLocator = 'tagged_locator' === $value->getTag();

                if (\is_array($argument) && isset($argument['tag']) && $argument['tag']) {
                    if ($diff = array_diff(array_keys($argument), ['tag', 'index_by', 'default_index_method', 'default_priority_method'])) {
                        throw new InvalidArgumentException(sprintf('"!%s" tag contains unsupported key "%s"; supported ones are "tag", "index_by", "default_index_method", and "default_priority_method".', $value->getTag(), implode('", "', $diff)));
                    }

                    $argument = new TaggedIteratorArgument($argument['tag'], $argument['index_by'] ?? null, $argument['default_index_method'] ?? null, $forLocator, $argument['default_priority_method'] ?? null);
                } elseif (\is_string($argument) && $argument) {
                    $argument = new TaggedIteratorArgument($argument, null, null, $forLocator);
                } else {
                    throw new InvalidArgumentException(sprintf('"!%s" tags only accept a non empty string or an array with a key "tag" in "%s".', $value->getTag(), $file));
                }

                if ($forLocator) {
                    $argument = new ServiceLocatorArgument($argument);
                }

                return $argument;
            }
            if ('service' === $value->getTag()) {
                if ($isParameter) {
                    throw new InvalidArgumentException(sprintf('Using an anonymous service in a parameter is not allowed in "%s".', $file));
                }

                $isLoadingInstanceof = $this->isLoadingInstanceof;
                $this->isLoadingInstanceof = false;
                $instanceof = $this->instanceof;
                $this->instanceof = [];

                $id = sprintf('.%d_%s', ++$this->anonymousServicesCount, preg_replace('/^.*\\\\/', '', $argument['class'] ?? '').$this->anonymousServicesSuffix);
                $this->parseDefinition($id, $argument, $file, []);

                if (!$this->container->hasDefinition($id)) {
                    throw new InvalidArgumentException(sprintf('Creating an alias using the tag "!service" is not allowed in "%s".', $file));
                }

                $this->container->getDefinition($id);

                $this->isLoadingInstanceof = $isLoadingInstanceof;
                $this->instanceof = $instanceof;

                return new Reference($id);
            }
            if ('abstract' === $value->getTag()) {
                return new AbstractArgument($value->getValue());
            }

            throw new InvalidArgumentException(sprintf('Unsupported tag "!%s".', $value->getTag()));
        }

        if (\is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->resolveServices($v, $file, $isParameter);
            }
        } elseif (\is_string($value) && str_starts_with($value, '@=')) {
            if ($isParameter) {
                throw new InvalidArgumentException(sprintf('Using expressions in parameters is not allowed in "%s".', $file));
            }

            if (!class_exists(Expression::class)) {
                throw new \LogicException('The "@=" expression syntax cannot be used without the ExpressionLanguage component. Try running "composer require symfony/expression-language".');
            }

            return new Expression(substr($value, 2));
        } elseif (\is_string($value) && str_starts_with($value, '@')) {
            if (str_starts_with($value, '@@')) {
                $value = substr($value, 1);
                $invalidBehavior = null;
            } elseif (str_starts_with($value, '@!')) {
                $value = substr($value, 2);
                $invalidBehavior = ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE;
            } elseif (str_starts_with($value, '@?')) {
                $value = substr($value, 2);
                $invalidBehavior = ContainerInterface::IGNORE_ON_INVALID_REFERENCE;
            } else {
                $value = substr($value, 1);
                $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
            }

            if (null !== $invalidBehavior) {
                $value = new Reference($value, $invalidBehavior);
            }
        }

        return $value;
    }

    private function loadFromExtensions(array $content)
    {
        foreach ($content as $namespace => $values) {
            if (\in_array($namespace, ['imports', 'parameters', 'services']) || 0 === strpos($namespace, 'when@')) {
                continue;
            }

            if (!\is_array($values) && null !== $values) {
                $values = [];
            }

            $this->container->loadFromExtension($namespace, $values);
        }
    }

    private function checkDefinition(string $id, array $definition, string $file)
    {
        if ($this->isLoadingInstanceof) {
            $keywords = self::INSTANCEOF_KEYWORDS;
        } elseif (isset($definition['resource']) || isset($definition['namespace'])) {
            $keywords = self::PROTOTYPE_KEYWORDS;
        } else {
            $keywords = self::SERVICE_KEYWORDS;
        }

        foreach ($definition as $key => $value) {
            if (!isset($keywords[$key])) {
                throw new InvalidArgumentException(sprintf('The configuration key "%s" is unsupported for definition "%s" in "%s". Allowed configuration keys are "%s".', $key, $id, $file, implode('", "', $keywords)));
            }
        }
    }
}
