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

    /**
     * {@inheritdoc}
     */
    public function load($resource, string $type = null)
    {
        $path = $this->locator->locate($resource);

        $xml = $this->parseFileToDOM($path);

        $this->container->fileExists($path);

        $defaults = $this->getServiceDefaults($xml, $path);

        // anonymous services
        $this->processAnonymousServices($xml, $path);

        // imports
        $this->parseImports($xml, $path);

        // parameters
        $this->parseParameters($xml, $path);

        // extensions
        $this->loadFromExtensions($xml);

        // services
        try {
            $this->parseDefinitions($xml, $path, $defaults);
        } finally {
            $this->instanceof = [];
            $this->registerAliasesForSinglyImplementedInterfaces();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports($resource, string $type = null)
    {
        if (!\is_string($resource)) {
            return false;
        }

        if (null === $type && 'xml' === pathinfo($resource, \PATHINFO_EXTENSION)) {
            return true;
        }

        return 'xml' === $type;
    }

    private function parseParameters(\DOMDocument $xml, string $file)
    {
        if ($parameters = $this->getChildren($xml->documentElement, 'parameters')) {
            $this->container->getParameterBag()->add($this->getArgumentsAsPhp($parameters[0], 'parameter', $file));
        }
    }

    private function parseImports(\DOMDocument $xml, string $file)
    {
        $xpath = new \DOMXPath($xml);
        $xpath->registerNamespace('container', self::NS);

        if (false === $imports = $xpath->query('//container:imports/container:import')) {
            return;
        }

        $defaultDirectory = \dirname($file);
        foreach ($imports as $import) {
            $this->setCurrentDir($defaultDirectory);
            $this->import($import->getAttribute('resource'), XmlUtils::phpize($import->getAttribute('type')) ?: null, XmlUtils::phpize($import->getAttribute('ignore-errors')) ?: false, $file);
        }
    }

    private function parseDefinitions(\DOMDocument $xml, string $file, Definition $defaults)
    {
        $xpath = new \DOMXPath($xml);
        $xpath->registerNamespace('container', self::NS);

        if (false === $services = $xpath->query('//container:services/container:service|//container:services/container:prototype|//container:services/container:stack')) {
            return;
        }
        $this->setCurrentDir(\dirname($file));

        $this->instanceof = [];
        $this->isLoadingInstanceof = true;
        $instanceof = $xpath->query('//container:services/container:instanceof');
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
                    $this->registerClasses($definition, (string) $service->getAttribute('namespace'), (string) $service->getAttribute('resource'), $excludes);
                } else {
                    $this->setDefinition((string) $service->getAttribute('id'), $definition);
                }
            }
        }
    }

    private function getServiceDefaults(\DOMDocument $xml, string $file): Definition
    {
        $xpath = new \DOMXPath($xml);
        $xpath->registerNamespace('container', self::NS);

        if (null === $defaultsNode = $xpath->query('//container:services/container:defaults')->item(0)) {
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

            $this->container->setAlias((string) $service->getAttribute('id'), $alias = new Alias($alias));
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
                    trigger_deprecation('symfony/dependency-injection', '5.1', 'Not setting the attribute "package" of the node "deprecated" in "%s" is deprecated.', $file);
                }

                if (!$deprecated[0]->hasAttribute('version')) {
                    trigger_deprecation('symfony/dependency-injection', '5.1', 'Not setting the attribute "version" of the node "deprecated" in "%s" is deprecated.', $file);
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

            if ('' === $package) {
                trigger_deprecation('symfony/dependency-injection', '5.1', 'Not setting the attribute "package" of the node "deprecated" in "%s" is deprecated.', $file);
            }

            if ('' === $version) {
                trigger_deprecation('symfony/dependency-injection', '5.1', 'Not setting the attribute "version" of the node "deprecated" in "%s" is deprecated.', $file);
            }

            $definition->setDeprecated($package, $version, $message);
        }

        $definition->setArguments($this->getArgumentsAsPhp($service, 'argument', $file, $definition instanceof ChildDefinition));
        $definition->setProperties($this->getArgumentsAsPhp($service, 'property', $file));

        if ($factories = $this->getChildren($service, 'factory')) {
            $factory = $factories[0];
            if ($function = $factory->getAttribute('function')) {
                $definition->setFactory($function);
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
            $parameters = [];
            $tagName = $tag->nodeValue;
            foreach ($tag->attributes as $name => $node) {
                if ('name' === $name && '' === $tagName) {
                    continue;
                }

                if (false !== strpos($name, '-') && false === strpos($name, '_') && !\array_key_exists($normalizedName = str_replace('-', '_', $name), $parameters)) {
                    $parameters[$normalizedName] = XmlUtils::phpize($node->nodeValue);
                }
                // keep not normalized key
                $parameters[$name] = XmlUtils::phpize($node->nodeValue);
            }

            if ('' === $tagName && '' === $tagName = $tag->getAttribute('name')) {
                throw new InvalidArgumentException(sprintf('The tag name for service "%s" in "%s" must be a non-empty string.', (string) $service->getAttribute('id'), $file));
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
                throw new InvalidArgumentException(sprintf('Invalid value "%s" for attribute "decoration-on-invalid" on service "%s". Did you mean "exception", "ignore" or "null" in "%s"?', $decorationOnInvalid, (string) $service->getAttribute('id'), $file));
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
            $dom = XmlUtils::loadFile($file, [$this, 'validateSchema']);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidArgumentException(sprintf('Unable to parse file "%s": ', $file).$e->getMessage(), $e->getCode(), $e);
        }

        $this->validateExtensions($dom, $file);

        return $dom;
    }

    /**
     * Processes anonymous services.
     */
    private function processAnonymousServices(\DOMDocument $xml, string $file)
    {
        $definitions = [];
        $count = 0;
        $suffix = '~'.ContainerBuilder::hash($file);

        $xpath = new \DOMXPath($xml);
        $xpath->registerNamespace('container', self::NS);

        // anonymous services as arguments/properties
        if (false !== $nodes = $xpath->query('//container:argument[@type="service"][not(@id)]|//container:property[@type="service"][not(@id)]|//container:bind[not(@id)]|//container:factory[not(@service)]|//container:configurator[not(@service)]')) {
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
        if (false !== $nodes = $xpath->query('//container:services/container:service[not(@id)]')) {
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

            switch ($arg->getAttribute('type')) {
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
                    try {
                        $arguments[$key] = new IteratorArgument($arg);
                    } catch (InvalidArgumentException $e) {
                        throw new InvalidArgumentException(sprintf('Tag "<%s>" with type="iterator" only accepts collections of type="service" references in "%s".', $name, $file));
                    }
                    break;
                case 'service_locator':
                    $arg = $this->getArgumentsAsPhp($arg, $name, $file);
                    try {
                        $arguments[$key] = new ServiceLocatorArgument($arg);
                    } catch (InvalidArgumentException $e) {
                        throw new InvalidArgumentException(sprintf('Tag "<%s>" with type="service_locator" only accepts maps of type="service" references in "%s".', $name, $file));
                    }
                    break;
                case 'tagged':
                case 'tagged_iterator':
                case 'tagged_locator':
                    $type = $arg->getAttribute('type');
                    $forLocator = 'tagged_locator' === $type;

                    if (!$arg->getAttribute('tag')) {
                        throw new InvalidArgumentException(sprintf('Tag "<%s>" with type="%s" has no or empty "tag" attribute in "%s".', $name, $type, $file));
                    }

                    $arguments[$key] = new TaggedIteratorArgument($arg->getAttribute('tag'), $arg->getAttribute('index-by') ?: null, $arg->getAttribute('default-index-method') ?: null, $forLocator, $arg->getAttribute('default-priority-method') ?: null);

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

    /**
     * Validates a documents XML schema.
     *
     * @return bool
     *
     * @throws RuntimeException When extension references a non-existent XSD file
     */
    public function validateSchema(\DOMDocument $dom)
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
            } elseif ('\\' === \DIRECTORY_SEPARATOR && 0 === strpos($location, '\\\\')) {
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
        // Version prior to 8.0 can be enabled without deprecation
        if (\PHP_VERSION_ID < 80000) {
            return true;
        }

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
  <xsd:include schemaLocation="file:///'.str_replace('\\', '/', $tmpfile).'" />
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
     *
     * @return mixed
     */
    public static function convertDomElementToArray(\DOMElement $element)
    {
        return XmlUtils::convertDomElementToArray($element);
    }
}
