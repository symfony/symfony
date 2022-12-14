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

use Symfony\Component\Config\Util\XmlUtils;
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

/**
 * XmlFileLoader loads XML files service definitions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class XmlFileLoader extends FileLoader
{
    public const NS = 'http://symfony.com/schema/dic/services';

    protected $autoRegisterAliasesForSinglyImplementedInterfaces = false;

    public function load(mixed $resource, string $type = null): mixed
    {
        $path = $this->locator->locate($resource);

        $xml = $this->parseFileToDOM($path);

        $this->container->fileExists($path);

        $this->loadXml($xml, $path);

        if ($this->env) {
            $xpath = new \DOMXPath($xml);
            $xpath->registerNamespace('container', self::NS);
            foreach ($xpath->query(sprintf('//container:when[@env="%s"]', $this->env)) ?: [] as $root) {
                $env = $this->env;
                $this->env = null;
                try {
                    $this->loadXml($xml, $path, $root);
                } finally {
                    $this->env = $env;
                }
            }
        }

        return null;
    }

    private function loadXml(\DOMDocument $xml, string $path, \DOMNode $root = null): void
    {
        $defaults = $this->getServiceDefaults($xml, $path, $root);

        // anonymous services
        $this->processAnonymousServices($xml, $path, $root);

        // imports
        $this->parseImports($xml, $path, $root);

        // parameters
        $this->parseParameters($xml, $path, $root);

        // extensions
        $this->loadFromExtensions($xml, $root);

        // services
        try {
            $this->parseDefinitions($xml, $path, $defaults, $root);
        } finally {
            $this->instanceof = [];
            $this->registerAliasesForSinglyImplementedInterfaces();
        }
    }

    public function supports(mixed $resource, string $type = null): bool
    {
        if (!\is_string($resource)) {
            return false;
        }

        if (null === $type && 'xml' === pathinfo($resource, \PATHINFO_EXTENSION)) {
            return true;
        }

        return 'xml' === $type;
    }

    private function parseParameters(\DOMDocument $xml, string $file, \DOMNode $root = null)
    {
        if ($parameters = $this->getChildren($root ?? $xml->documentElement, 'parameters')) {
            $this->container->getParameterBag()->add($this->getArgumentsAsPhp($parameters[0], 'parameter', $file));
        }
    }

    private function parseImports(\DOMDocument $xml, string $file, \DOMNode $root = null)
    {
        $xpath = new \DOMXPath($xml);
        $xpath->registerNamespace('container', self::NS);

        if (false === $imports = $xpath->query('.//container:imports/container:import', $root)) {
            return;
        }

        $defaultDirectory = \dirname($file);
        foreach ($imports as $import) {
            $this->setCurrentDir($defaultDirectory);
            $this->import($import->getAttribute('resource'), XmlUtils::phpize($import->getAttribute('type')) ?: null, XmlUtils::phpize($import->getAttribute('ignore-errors')) ?: false, $file);
        }
    }

    private function parseDefinitions(\DOMDocument $xml, string $file, Definition $defaults, \DOMNode $root = null)
    {
        $xpath = new \DOMXPath($xml);
        $xpath->registerNamespace('container', self::NS);

        if (false === $services = $xpath->query('.//container:services/container:service|.//container:services/container:prototype|.//container:services/container:stack', $root)) {
            return;
        }
        $this->setCurrentDir(\dirname($file));

        $this->instanceof = [];
        $this->isLoadingInstanceof = true;
        $instanceof = $xpath->query('.//container:services/container:instanceof', $root);
        foreach ($instanceof as $service) {
            $this->setDefinition((string) $service->getAttribute('id'), $this->parseDefinition($service, $file, new Definition()));
        }

        $this->isLoadingInstanceof = false;
        foreach ($services as $service) {
            if ('stack' === $service->tagName) {
                $service->setAttribute('parent', '-');
                $definition = $this->parseDefinition($service, $file, $defaults)
                    ->setTags(array_merge_recursive(['container.stack' => [[]]], $defaults->getTags()))
                ;
                $this->setDefinition($id = (string) $service->getAttribute('id'), $definition);
                $stack = [];

                foreach ($this->getChildren($service, 'service') as $k => $frame) {
                    $k = $frame->getAttribute('id') ?: $k;
                    $frame->setAttribute('id', $id.'" at index "'.$k);

                    if ($alias = $frame->getAttribute('alias')) {
                        $this->validateAlias($frame, $file);
                        $stack[$k] = new Reference($alias);
                    } else {
                        $stack[$k] = $this->parseDefinition($frame, $file, $defaults)
                            ->setInstanceofConditionals($this->instanceof);
                    }
                }

                $definition->setArguments($stack);
            } elseif (null !== $definition = $this->parseDefinition($service, $file, $defaults)) {
                if ('prototype' === $service->tagName) {
                    $excludes = array_column($this->getChildren($service, 'exclude'), 'nodeValue');
                    if ($service->hasAttribute('exclude')) {
                        if (\count($excludes) > 0) {
                            throw new InvalidArgumentException('You cannot use both the attribute "exclude" and <exclude> tags at the same time.');
                        }
                        $excludes = [$service->getAttribute('exclude')];
                    }
                    $this->registerClasses($definition, (string) $service->getAttribute('namespace'), (string) $service->getAttribute('resource'), $excludes, $file);
                } else {
                    $this->setDefinition((string) $service->getAttribute('id'), $definition);
                }
            }
        }
    }

    private function getServiceDefaults(\DOMDocument $xml, string $file, \DOMNode $root = null): Definition
    {
        $xpath = new \DOMXPath($xml);
        $xpath->registerNamespace('container', self::NS);

        if (null === $defaultsNode = $xpath->query('.//container:services/container:defaults', $root)->item(0)) {
            return new Definition();
        }

        $defaultsNode->setAttribute('id', '<defaults>');

        return $this->parseDefinition($defaultsNode, $file, new Definition());
    }

    /**
     * Parses an individual Definition.
     */
    private function parseDefinition(\DOMElement $service, string $file, Definition $defaults): ?Definition
    {
        if ($alias = $service->getAttribute('alias')) {
            $this->validateAlias($service, $file);

            $this->container->setAlias($service->getAttribute('id'), $alias = new Alias($alias));
            if ($publicAttr = $service->getAttribute('public')) {
                $alias->setPublic(XmlUtils::phpize($publicAttr));
            } elseif ($defaults->getChanges()['public'] ?? false) {
                $alias->setPublic($defaults->isPublic());
            }

            if ($deprecated = $this->getChildren($service, 'deprecated')) {
                $message = $deprecated[0]->nodeValue ?: '';
                $package = $deprecated[0]->getAttribute('package') ?: '';
                $version = $deprecated[0]->getAttribute('version') ?: '';

                if (!$deprecated[0]->hasAttribute('package')) {
                    throw new InvalidArgumentException(sprintf('Missing attribute "package" at node "deprecated" in "%s".', $file));
                }

                if (!$deprecated[0]->hasAttribute('version')) {
                    throw new InvalidArgumentException(sprintf('Missing attribute "version" at node "deprecated" in "%s".', $file));
                }

                $alias->setDeprecated($package, $version, $message);
            }

            return null;
        }

        if ($this->isLoadingInstanceof) {
            $definition = new ChildDefinition('');
        } elseif ($parent = $service->getAttribute('parent')) {
            $definition = new ChildDefinition($parent);
        } else {
            $definition = new Definition();
        }

        if ($defaults->getChanges()['public'] ?? false) {
            $definition->setPublic($defaults->isPublic());
        }
        $definition->setAutowired($defaults->isAutowired());
        $definition->setAutoconfigured($defaults->isAutoconfigured());
        $definition->setChanges([]);

        foreach (['class', 'public', 'shared', 'synthetic', 'abstract'] as $key) {
            if ($value = $service->getAttribute($key)) {
                $method = 'set'.$key;
                $definition->$method($value = XmlUtils::phpize($value));
            }
        }

        if ($value = $service->getAttribute('lazy')) {
            $definition->setLazy((bool) $value = XmlUtils::phpize($value));
            if (\is_string($value)) {
                $definition->addTag('proxy', ['interface' => $value]);
            }
        }

        if ($value = $service->getAttribute('autowire')) {
            $definition->setAutowired(XmlUtils::phpize($value));
        }

        if ($value = $service->getAttribute('autoconfigure')) {
            $definition->setAutoconfigured(XmlUtils::phpize($value));
        }

        if ($files = $this->getChildren($service, 'file')) {
            $definition->setFile($files[0]->nodeValue);
        }

        if ($deprecated = $this->getChildren($service, 'deprecated')) {
            $message = $deprecated[0]->nodeValue ?: '';
            $package = $deprecated[0]->getAttribute('package') ?: '';
            $version = $deprecated[0]->getAttribute('version') ?: '';

            if (!$deprecated[0]->hasAttribute('package')) {
                throw new InvalidArgumentException(sprintf('Missing attribute "package" at node "deprecated" in "%s".', $file));
            }

            if (!$deprecated[0]->hasAttribute('version')) {
                throw new InvalidArgumentException(sprintf('Missing attribute "version" at node "deprecated" in "%s".', $file));
            }

            $definition->setDeprecated($package, $version, $message);
        }

        $definition->setArguments($this->getArgumentsAsPhp($service, 'argument', $file, $definition instanceof ChildDefinition));
        $definition->setProperties($this->getArgumentsAsPhp($service, 'property', $file));

        if ($factories = $this->getChildren($service, 'factory')) {
            $factory = $factories[0];
            if ($function = $factory->getAttribute('function')) {
                $definition->setFactory($function);
            } elseif ($expression = $factory->getAttribute('expression')) {
                if (!class_exists(Expression::class)) {
                    throw new \LogicException('The "expression" attribute cannot be used on factories without the ExpressionLanguage component. Try running "composer require symfony/expression-language".');
                }
                $definition->setFactory('@='.$expression);
            } else {
                if ($childService = $factory->getAttribute('service')) {
                    $class = new Reference($childService, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);
                } else {
                    $class = $factory->hasAttribute('class') ? $factory->getAttribute('class') : null;
                }

                $definition->setFactory([$class, $factory->getAttribute('method') ?: '__invoke']);
            }
        }

        if ($configurators = $this->getChildren($service, 'configurator')) {
            $configurator = $configurators[0];
            if ($function = $configurator->getAttribute('function')) {
                $definition->setConfigurator($function);
            } else {
                if ($childService = $configurator->getAttribute('service')) {
                    $class = new Reference($childService, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);
                } else {
                    $class = $configurator->getAttribute('class');
                }

                $definition->setConfigurator([$class, $configurator->getAttribute('method') ?: '__invoke']);
            }
        }

        foreach ($this->getChildren($service, 'call') as $call) {
            $definition->addMethodCall($call->getAttribute('method'), $this->getArgumentsAsPhp($call, 'argument', $file), XmlUtils::phpize($call->getAttribute('returns-clone')));
        }

        $tags = $this->getChildren($service, 'tag');

        foreach ($tags as $tag) {
            if ('' === $tagName = $tag->childElementCount || '' === $tag->nodeValue ? $tag->getAttribute('name') : $tag->nodeValue) {
                throw new InvalidArgumentException(sprintf('The tag name for service "%s" in "%s" must be a non-empty string.', (string) $service->getAttribute('id'), $file));
            }

            $parameters = $this->getTagAttributes($tag, sprintf('The attribute name of tag "%s" for service "%s" in %s must be a non-empty string.', $tagName, (string) $service->getAttribute('id'), $file));
            foreach ($tag->attributes as $name => $node) {
                if ('name' === $name) {
                    continue;
                }

                if (str_contains($name, '-') && !str_contains($name, '_') && !\array_key_exists($normalizedName = str_replace('-', '_', $name), $parameters)) {
                    $parameters[$normalizedName] = XmlUtils::phpize($node->nodeValue);
                }
                // keep not normalized key
                $parameters[$name] = XmlUtils::phpize($node->nodeValue);
            }

            $definition->addTag($tagName, $parameters);
        }

        $definition->setTags(array_merge_recursive($definition->getTags(), $defaults->getTags()));

        $bindings = $this->getArgumentsAsPhp($service, 'bind', $file);
        $bindingType = $this->isLoadingInstanceof ? BoundArgument::INSTANCEOF_BINDING : BoundArgument::SERVICE_BINDING;
        foreach ($bindings as $argument => $value) {
            $bindings[$argument] = new BoundArgument($value, true, $bindingType, $file);
        }

        // deep clone, to avoid multiple process of the same instance in the passes
        $bindings = array_merge(unserialize(serialize($defaults->getBindings())), $bindings);

        if ($bindings) {
            $definition->setBindings($bindings);
        }

        if ($decorates = $service->getAttribute('decorates')) {
            $decorationOnInvalid = $service->getAttribute('decoration-on-invalid') ?: 'exception';
            if ('exception' === $decorationOnInvalid) {
                $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
            } elseif ('ignore' === $decorationOnInvalid) {
                $invalidBehavior = ContainerInterface::IGNORE_ON_INVALID_REFERENCE;
            } elseif ('null' === $decorationOnInvalid) {
                $invalidBehavior = ContainerInterface::NULL_ON_INVALID_REFERENCE;
            } else {
                throw new InvalidArgumentException(sprintf('Invalid value "%s" for attribute "decoration-on-invalid" on service "%s". Did you mean "exception", "ignore" or "null" in "%s"?', $decorationOnInvalid, $service->getAttribute('id'), $file));
            }

            $renameId = $service->hasAttribute('decoration-inner-name') ? $service->getAttribute('decoration-inner-name') : null;
            $priority = $service->hasAttribute('decoration-priority') ? $service->getAttribute('decoration-priority') : 0;

            $definition->setDecoratedService($decorates, $renameId, $priority, $invalidBehavior);
        }

        return $definition;
    }

    /**
     * Parses an XML file to a \DOMDocument.
     *
     * @throws InvalidArgumentException When loading of XML file returns error
     */
    private function parseFileToDOM(string $file): \DOMDocument
    {
        try {
            $dom = XmlUtils::loadFile($file, $this->validateSchema(...));
        } catch (\InvalidArgumentException $e) {
            throw new InvalidArgumentException(sprintf('Unable to parse file "%s": ', $file).$e->getMessage(), $e->getCode(), $e);
        }

        $this->validateExtensions($dom, $file);

        return $dom;
    }

    /**
     * Processes anonymous services.
     */
    private function processAnonymousServices(\DOMDocument $xml, string $file, \DOMNode $root = null)
    {
        $definitions = [];
        $count = 0;
        $suffix = '~'.ContainerBuilder::hash($file);

        $xpath = new \DOMXPath($xml);
        $xpath->registerNamespace('container', self::NS);

        // anonymous services as arguments/properties
        if (false !== $nodes = $xpath->query('.//container:argument[@type="service"][not(@id)]|.//container:property[@type="service"][not(@id)]|.//container:bind[not(@id)]|.//container:factory[not(@service)]|.//container:configurator[not(@service)]', $root)) {
            foreach ($nodes as $node) {
                if ($services = $this->getChildren($node, 'service')) {
                    // give it a unique name
                    $id = sprintf('.%d_%s', ++$count, preg_replace('/^.*\\\\/', '', $services[0]->getAttribute('class')).$suffix);
                    $node->setAttribute('id', $id);
                    $node->setAttribute('service', $id);

                    $definitions[$id] = [$services[0], $file];
                    $services[0]->setAttribute('id', $id);

                    // anonymous services are always private
                    // we could not use the constant false here, because of XML parsing
                    $services[0]->setAttribute('public', 'false');
                }
            }
        }

        // anonymous services "in the wild"
        if (false !== $nodes = $xpath->query('.//container:services/container:service[not(@id)]', $root)) {
            foreach ($nodes as $node) {
                throw new InvalidArgumentException(sprintf('Top-level services must have "id" attribute, none found in "%s" at line %d.', $file, $node->getLineNo()));
            }
        }

        // resolve definitions
        uksort($definitions, 'strnatcmp');
        foreach (array_reverse($definitions) as $id => [$domElement, $file]) {
            if (null !== $definition = $this->parseDefinition($domElement, $file, new Definition())) {
                $this->setDefinition($id, $definition);
            }
        }
    }

    private function getArgumentsAsPhp(\DOMElement $node, string $name, string $file, bool $isChildDefinition = false): array
    {
        $arguments = [];
        foreach ($this->getChildren($node, $name) as $arg) {
            if ($arg->hasAttribute('name')) {
                $arg->setAttribute('key', $arg->getAttribute('name'));
            }

            // this is used by ChildDefinition to overwrite a specific
            // argument of the parent definition
            if ($arg->hasAttribute('index')) {
                $key = ($isChildDefinition ? 'index_' : '').$arg->getAttribute('index');
            } elseif (!$arg->hasAttribute('key')) {
                // Append an empty argument, then fetch its key to overwrite it later
                $arguments[] = null;
                $keys = array_keys($arguments);
                $key = array_pop($keys);
            } else {
                $key = $arg->getAttribute('key');
            }

            $onInvalid = $arg->getAttribute('on-invalid');
            $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
            if ('ignore' == $onInvalid) {
                $invalidBehavior = ContainerInterface::IGNORE_ON_INVALID_REFERENCE;
            } elseif ('ignore_uninitialized' == $onInvalid) {
                $invalidBehavior = ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE;
            } elseif ('null' == $onInvalid) {
                $invalidBehavior = ContainerInterface::NULL_ON_INVALID_REFERENCE;
            }

            switch ($type = $arg->getAttribute('type')) {
                case 'service':
                    if ('' === $arg->getAttribute('id')) {
                        throw new InvalidArgumentException(sprintf('Tag "<%s>" with type="service" has no or empty "id" attribute in "%s".', $name, $file));
                    }

                    $arguments[$key] = new Reference($arg->getAttribute('id'), $invalidBehavior);
                    break;
                case 'expression':
                    if (!class_exists(Expression::class)) {
                        throw new \LogicException('The type="expression" attribute cannot be used without the ExpressionLanguage component. Try running "composer require symfony/expression-language".');
                    }

                    $arguments[$key] = new Expression($arg->nodeValue);
                    break;
                case 'collection':
                    $arguments[$key] = $this->getArgumentsAsPhp($arg, $name, $file);
                    break;
                case 'iterator':
                    $arg = $this->getArgumentsAsPhp($arg, $name, $file);
                    $arguments[$key] = new IteratorArgument($arg);
                    break;
                case 'closure':
                case 'service_closure':
                    if ('' !== $arg->getAttribute('id')) {
                        $arg = new Reference($arg->getAttribute('id'), $invalidBehavior);
                    } else {
                        $arg = $this->getArgumentsAsPhp($arg, $name, $file);
                    }
                    $arguments[$key] = match ($type) {
                        'service_closure' => new ServiceClosureArgument($arg),
                        'closure' => (new Definition('Closure'))
                            ->setFactory(['Closure', 'fromCallable'])
                            ->addArgument($arg),
                    };
                    break;
                case 'service_locator':
                    $arg = $this->getArgumentsAsPhp($arg, $name, $file);
                    $arguments[$key] = new ServiceLocatorArgument($arg);
                    break;
                case 'tagged':
                case 'tagged_iterator':
                case 'tagged_locator':
                    $forLocator = 'tagged_locator' === $type;

                    if (!$arg->getAttribute('tag')) {
                        throw new InvalidArgumentException(sprintf('Tag "<%s>" with type="%s" has no or empty "tag" attribute in "%s".', $name, $type, $file));
                    }

                    $excludes = array_column($this->getChildren($arg, 'exclude'), 'nodeValue');
                    if ($arg->hasAttribute('exclude')) {
                        if (\count($excludes) > 0) {
                            throw new InvalidArgumentException('You cannot use both the attribute "exclude" and <exclude> tags at the same time.');
                        }
                        $excludes = [$arg->getAttribute('exclude')];
                    }

                    $arguments[$key] = new TaggedIteratorArgument($arg->getAttribute('tag'), $arg->getAttribute('index-by') ?: null, $arg->getAttribute('default-index-method') ?: null, $forLocator, $arg->getAttribute('default-priority-method') ?: null, $excludes);

                    if ($forLocator) {
                        $arguments[$key] = new ServiceLocatorArgument($arguments[$key]);
                    }
                    break;
                case 'binary':
                    if (false === $value = base64_decode($arg->nodeValue)) {
                        throw new InvalidArgumentException(sprintf('Tag "<%s>" with type="binary" is not a valid base64 encoded string.', $name));
                    }
                    $arguments[$key] = $value;
                    break;
                case 'abstract':
                    $arguments[$key] = new AbstractArgument($arg->nodeValue);
                    break;
                case 'string':
                    $arguments[$key] = $arg->nodeValue;
                    break;
                case 'constant':
                    $arguments[$key] = \constant(trim($arg->nodeValue));
                    break;
                default:
                    $arguments[$key] = XmlUtils::phpize($arg->nodeValue);
            }
        }

        return $arguments;
    }

    /**
     * Get child elements by name.
     *
     * @return \DOMElement[]
     */
    private function getChildren(\DOMNode $node, string $name): array
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $name && self::NS === $child->namespaceURI) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private function getTagAttributes(\DOMNode $node, string $missingName): array
    {
        $parameters = [];
        $children = $this->getChildren($node, 'attribute');

        foreach ($children as $childNode) {
            if ('' === $name = $childNode->getAttribute('name')) {
                throw new InvalidArgumentException($missingName);
            }

            if ($this->getChildren($childNode, 'attribute')) {
                $parameters[$name] = $this->getTagAttributes($childNode, $missingName);
            } else {
                if (str_contains($name, '-') && !str_contains($name, '_') && !\array_key_exists($normalizedName = str_replace('-', '_', $name), $parameters)) {
                    $parameters[$normalizedName] = XmlUtils::phpize($childNode->nodeValue);
                }
                // keep not normalized key
                $parameters[$name] = XmlUtils::phpize($childNode->nodeValue);
            }
        }

        return $parameters;
    }

    /**
     * Validates a documents XML schema.
     *
     * @throws RuntimeException When extension references a non-existent XSD file
     */
    public function validateSchema(\DOMDocument $dom): bool
    {
        $schemaLocations = ['http://symfony.com/schema/dic/services' => str_replace('\\', '/', __DIR__.'/schema/dic/services/services-1.0.xsd')];

        if ($element = $dom->documentElement->getAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'schemaLocation')) {
            $items = preg_split('/\s+/', $element);
            for ($i = 0, $nb = \count($items); $i < $nb; $i += 2) {
                if (!$this->container->hasExtension($items[$i])) {
                    continue;
                }

                if (($extension = $this->container->getExtension($items[$i])) && false !== $extension->getXsdValidationBasePath()) {
                    $ns = $extension->getNamespace();
                    $path = str_replace([$ns, str_replace('http://', 'https://', $ns)], str_replace('\\', '/', $extension->getXsdValidationBasePath()).'/', $items[$i + 1]);

                    if (!is_file($path)) {
                        throw new RuntimeException(sprintf('Extension "%s" references a non-existent XSD file "%s".', get_debug_type($extension), $path));
                    }

                    $schemaLocations[$items[$i]] = $path;
                }
            }
        }

        $tmpfiles = [];
        $imports = '';
        foreach ($schemaLocations as $namespace => $location) {
            $parts = explode('/', $location);
            $locationstart = 'file:///';
            if (0 === stripos($location, 'phar://')) {
                $tmpfile = tempnam(sys_get_temp_dir(), 'symfony');
                if ($tmpfile) {
                    copy($location, $tmpfile);
                    $tmpfiles[] = $tmpfile;
                    $parts = explode('/', str_replace('\\', '/', $tmpfile));
                } else {
                    array_shift($parts);
                    $locationstart = 'phar:///';
                }
            } elseif ('\\' === \DIRECTORY_SEPARATOR && str_starts_with($location, '\\\\')) {
                $locationstart = '';
            }
            $drive = '\\' === \DIRECTORY_SEPARATOR ? array_shift($parts).'/' : '';
            $location = $locationstart.$drive.implode('/', array_map('rawurlencode', $parts));

            $imports .= sprintf('  <xsd:import namespace="%s" schemaLocation="%s" />'."\n", $namespace, $location);
        }

        $source = <<<EOF
<?xml version="1.0" encoding="utf-8" ?>
<xsd:schema xmlns="http://symfony.com/schema"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    targetNamespace="http://symfony.com/schema"
    elementFormDefault="qualified">

    <xsd:import namespace="http://www.w3.org/XML/1998/namespace"/>
$imports
</xsd:schema>
EOF
        ;

        if ($this->shouldEnableEntityLoader()) {
            $disableEntities = libxml_disable_entity_loader(false);
            $valid = @$dom->schemaValidateSource($source);
            libxml_disable_entity_loader($disableEntities);
        } else {
            $valid = @$dom->schemaValidateSource($source);
        }
        foreach ($tmpfiles as $tmpfile) {
            @unlink($tmpfile);
        }

        return $valid;
    }

    private function shouldEnableEntityLoader(): bool
    {
        static $dom, $schema;
        if (null === $dom) {
            $dom = new \DOMDocument();
            $dom->loadXML('<?xml version="1.0"?><test/>');

            $tmpfile = tempnam(sys_get_temp_dir(), 'symfony');
            register_shutdown_function(static function () use ($tmpfile) {
                @unlink($tmpfile);
            });
            $schema = '<?xml version="1.0" encoding="utf-8"?>
<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <xsd:include schemaLocation="file:///'.rawurlencode(str_replace('\\', '/', $tmpfile)).'" />
</xsd:schema>';
            file_put_contents($tmpfile, '<?xml version="1.0" encoding="utf-8"?>
<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <xsd:element name="test" type="testType" />
  <xsd:complexType name="testType"/>
</xsd:schema>');
        }

        return !@$dom->schemaValidateSource($schema);
    }

    private function validateAlias(\DOMElement $alias, string $file)
    {
        foreach ($alias->attributes as $name => $node) {
            if (!\in_array($name, ['alias', 'id', 'public'])) {
                throw new InvalidArgumentException(sprintf('Invalid attribute "%s" defined for alias "%s" in "%s".', $name, $alias->getAttribute('id'), $file));
            }
        }

        foreach ($alias->childNodes as $child) {
            if (!$child instanceof \DOMElement || self::NS !== $child->namespaceURI) {
                continue;
            }
            if (!\in_array($child->localName, ['deprecated'], true)) {
                throw new InvalidArgumentException(sprintf('Invalid child element "%s" defined for alias "%s" in "%s".', $child->localName, $alias->getAttribute('id'), $file));
            }
        }
    }

    /**
     * Validates an extension.
     *
     * @throws InvalidArgumentException When no extension is found corresponding to a tag
     */
    private function validateExtensions(\DOMDocument $dom, string $file)
    {
        foreach ($dom->documentElement->childNodes as $node) {
            if (!$node instanceof \DOMElement || 'http://symfony.com/schema/dic/services' === $node->namespaceURI) {
                continue;
            }

            // can it be handled by an extension?
            if (!$this->container->hasExtension($node->namespaceURI)) {
                $extensionNamespaces = array_filter(array_map(function (ExtensionInterface $ext) { return $ext->getNamespace(); }, $this->container->getExtensions()));
                throw new InvalidArgumentException(sprintf('There is no extension able to load the configuration for "%s" (in "%s"). Looked for namespace "%s", found "%s".', $node->tagName, $file, $node->namespaceURI, $extensionNamespaces ? implode('", "', $extensionNamespaces) : 'none'));
            }
        }
    }

    /**
     * Loads from an extension.
     */
    private function loadFromExtensions(\DOMDocument $xml)
    {
        foreach ($xml->documentElement->childNodes as $node) {
            if (!$node instanceof \DOMElement || self::NS === $node->namespaceURI) {
                continue;
            }

            $values = static::convertDomElementToArray($node);
            if (!\is_array($values)) {
                $values = [];
            }

            $this->container->loadFromExtension($node->namespaceURI, $values);
        }
    }

    /**
     * Converts a \DOMElement object to a PHP array.
     *
     * The following rules applies during the conversion:
     *
     *  * Each tag is converted to a key value or an array
     *    if there is more than one "value"
     *
     *  * The content of a tag is set under a "value" key (<foo>bar</foo>)
     *    if the tag also has some nested tags
     *
     *  * The attributes are converted to keys (<foo foo="bar"/>)
     *
     *  * The nested-tags are converted to keys (<foo><foo>bar</foo></foo>)
     *
     * @param \DOMElement $element A \DOMElement instance
     */
    public static function convertDomElementToArray(\DOMElement $element): mixed
    {
        return XmlUtils::convertDomElementToArray($element);
    }
}
