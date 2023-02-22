<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Console\Descriptor;

use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class XmlDescriptor extends Descriptor
{
    protected function describeRouteCollection(RouteCollection $routes, array $options = []): void
    {
        $this->writeDocument($this->getRouteCollectionDocument($routes));
    }

    protected function describeRoute(Route $route, array $options = []): void
    {
        $this->writeDocument($this->getRouteDocument($route, $options['name'] ?? null));
    }

    protected function describeContainerParameters(ParameterBag $parameters, array $options = []): void
    {
        $this->writeDocument($this->getContainerParametersDocument($parameters));
    }

    protected function describeContainerTags(ContainerBuilder $builder, array $options = []): void
    {
        $this->writeDocument($this->getContainerTagsDocument($builder, isset($options['show_hidden']) && $options['show_hidden']));
    }

    protected function describeContainerService(object $service, array $options = [], ContainerBuilder $builder = null): void
    {
        if (!isset($options['id'])) {
            throw new \InvalidArgumentException('An "id" option must be provided.');
        }

        $this->writeDocument($this->getContainerServiceDocument($service, $options['id'], $builder, isset($options['show_arguments']) && $options['show_arguments']));
    }

    protected function describeContainerServices(ContainerBuilder $builder, array $options = []): void
    {
        $this->writeDocument($this->getContainerServicesDocument($builder, $options['tag'] ?? null, isset($options['show_hidden']) && $options['show_hidden'], isset($options['show_arguments']) && $options['show_arguments'], $options['filter'] ?? null, $options['id'] ?? null));
    }

    protected function describeContainerDefinition(Definition $definition, array $options = [], ContainerBuilder $builder = null): void
    {
        $this->writeDocument($this->getContainerDefinitionDocument($definition, $options['id'] ?? null, isset($options['omit_tags']) && $options['omit_tags'], isset($options['show_arguments']) && $options['show_arguments'], $builder));
    }

    protected function describeContainerAlias(Alias $alias, array $options = [], ContainerBuilder $builder = null): void
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($dom->importNode($this->getContainerAliasDocument($alias, $options['id'] ?? null)->childNodes->item(0), true));

        if (!$builder) {
            $this->writeDocument($dom);

            return;
        }

        $dom->appendChild($dom->importNode($this->getContainerDefinitionDocument($builder->getDefinition((string) $alias), (string) $alias, false, false, $builder)->childNodes->item(0), true));

        $this->writeDocument($dom);
    }

    protected function describeEventDispatcherListeners(EventDispatcherInterface $eventDispatcher, array $options = []): void
    {
        $this->writeDocument($this->getEventDispatcherListenersDocument($eventDispatcher, $options));
    }

    protected function describeCallable(mixed $callable, array $options = []): void
    {
        $this->writeDocument($this->getCallableDocument($callable));
    }

    protected function describeContainerParameter(mixed $parameter, array $options = []): void
    {
        $this->writeDocument($this->getContainerParameterDocument($parameter, $options));
    }

    protected function describeContainerEnvVars(array $envs, array $options = []): void
    {
        throw new LogicException('Using the XML format to debug environment variables is not supported.');
    }

    protected function describeContainerDeprecations(ContainerBuilder $builder, array $options = []): void
    {
        $containerDeprecationFilePath = sprintf('%s/%sDeprecations.log', $builder->getParameter('kernel.build_dir'), $builder->getParameter('kernel.container_class'));
        if (!file_exists($containerDeprecationFilePath)) {
            throw new RuntimeException('The deprecation file does not exist, please try warming the cache first.');
        }

        $logs = unserialize(file_get_contents($containerDeprecationFilePath));

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($deprecationsXML = $dom->createElement('deprecations'));

        $remainingCount = 0;
        foreach ($logs as $log) {
            $deprecationsXML->appendChild($deprecationXML = $dom->createElement('deprecation'));
            $deprecationXML->setAttribute('count', $log['count']);
            $deprecationXML->appendChild($dom->createElement('message', $log['message']));
            $deprecationXML->appendChild($dom->createElement('file', $log['file']));
            $deprecationXML->appendChild($dom->createElement('line', $log['line']));
            $remainingCount += $log['count'];
        }

        $deprecationsXML->setAttribute('remainingCount', $remainingCount);

        $this->writeDocument($dom);
    }

    private function writeDocument(\DOMDocument $dom): void
    {
        $dom->formatOutput = true;
        $this->write($dom->saveXML());
    }

    private function getRouteCollectionDocument(RouteCollection $routes): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($routesXML = $dom->createElement('routes'));

        foreach ($routes->all() as $name => $route) {
            $routeXML = $this->getRouteDocument($route, $name);
            $routesXML->appendChild($routesXML->ownerDocument->importNode($routeXML->childNodes->item(0), true));
        }

        return $dom;
    }

    private function getRouteDocument(Route $route, string $name = null): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($routeXML = $dom->createElement('route'));

        if ($name) {
            $routeXML->setAttribute('name', $name);
        }

        $routeXML->setAttribute('class', $route::class);

        $routeXML->appendChild($pathXML = $dom->createElement('path'));
        $pathXML->setAttribute('regex', $route->compile()->getRegex());
        $pathXML->appendChild(new \DOMText($route->getPath()));

        if ('' !== $route->getHost()) {
            $routeXML->appendChild($hostXML = $dom->createElement('host'));
            $hostXML->setAttribute('regex', $route->compile()->getHostRegex());
            $hostXML->appendChild(new \DOMText($route->getHost()));
        }

        foreach ($route->getSchemes() as $scheme) {
            $routeXML->appendChild($schemeXML = $dom->createElement('scheme'));
            $schemeXML->appendChild(new \DOMText($scheme));
        }

        foreach ($route->getMethods() as $method) {
            $routeXML->appendChild($methodXML = $dom->createElement('method'));
            $methodXML->appendChild(new \DOMText($method));
        }

        if ($route->getDefaults()) {
            $routeXML->appendChild($defaultsXML = $dom->createElement('defaults'));
            foreach ($route->getDefaults() as $attribute => $value) {
                $defaultsXML->appendChild($defaultXML = $dom->createElement('default'));
                $defaultXML->setAttribute('key', $attribute);
                $defaultXML->appendChild(new \DOMText($this->formatValue($value)));
            }
        }

        $originRequirements = $requirements = $route->getRequirements();
        unset($requirements['_scheme'], $requirements['_method']);
        if ($requirements) {
            $routeXML->appendChild($requirementsXML = $dom->createElement('requirements'));
            foreach ($originRequirements as $attribute => $pattern) {
                $requirementsXML->appendChild($requirementXML = $dom->createElement('requirement'));
                $requirementXML->setAttribute('key', $attribute);
                $requirementXML->appendChild(new \DOMText($pattern));
            }
        }

        if ($route->getOptions()) {
            $routeXML->appendChild($optionsXML = $dom->createElement('options'));
            foreach ($route->getOptions() as $name => $value) {
                $optionsXML->appendChild($optionXML = $dom->createElement('option'));
                $optionXML->setAttribute('key', $name);
                $optionXML->appendChild(new \DOMText($this->formatValue($value)));
            }
        }

        if ('' !== $route->getCondition()) {
            $routeXML->appendChild($conditionXML = $dom->createElement('condition'));
            $conditionXML->appendChild(new \DOMText($route->getCondition()));
        }

        return $dom;
    }

    private function getContainerParametersDocument(ParameterBag $parameters): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($parametersXML = $dom->createElement('parameters'));

        foreach ($this->sortParameters($parameters) as $key => $value) {
            $parametersXML->appendChild($parameterXML = $dom->createElement('parameter'));
            $parameterXML->setAttribute('key', $key);
            $parameterXML->appendChild(new \DOMText($this->formatParameter($value)));
        }

        return $dom;
    }

    private function getContainerTagsDocument(ContainerBuilder $builder, bool $showHidden = false): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($containerXML = $dom->createElement('container'));

        foreach ($this->findDefinitionsByTag($builder, $showHidden) as $tag => $definitions) {
            $containerXML->appendChild($tagXML = $dom->createElement('tag'));
            $tagXML->setAttribute('name', $tag);

            foreach ($definitions as $serviceId => $definition) {
                $definitionXML = $this->getContainerDefinitionDocument($definition, $serviceId, true, false, $builder);
                $tagXML->appendChild($dom->importNode($definitionXML->childNodes->item(0), true));
            }
        }

        return $dom;
    }

    private function getContainerServiceDocument(object $service, string $id, ContainerBuilder $builder = null, bool $showArguments = false): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');

        if ($service instanceof Alias) {
            $dom->appendChild($dom->importNode($this->getContainerAliasDocument($service, $id)->childNodes->item(0), true));
            if ($builder) {
                $dom->appendChild($dom->importNode($this->getContainerDefinitionDocument($builder->getDefinition((string) $service), (string) $service, false, $showArguments, $builder)->childNodes->item(0), true));
            }
        } elseif ($service instanceof Definition) {
            $dom->appendChild($dom->importNode($this->getContainerDefinitionDocument($service, $id, false, $showArguments, $builder)->childNodes->item(0), true));
        } else {
            $dom->appendChild($serviceXML = $dom->createElement('service'));
            $serviceXML->setAttribute('id', $id);
            $serviceXML->setAttribute('class', $service::class);
        }

        return $dom;
    }

    private function getContainerServicesDocument(ContainerBuilder $builder, string $tag = null, bool $showHidden = false, bool $showArguments = false, callable $filter = null, string $id = null): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($containerXML = $dom->createElement('container'));

        $serviceIds = $tag
            ? $this->sortTaggedServicesByPriority($builder->findTaggedServiceIds($tag))
            : $this->sortServiceIds($builder->getServiceIds());
        if ($filter) {
            $serviceIds = array_filter($serviceIds, $filter);
        }

        foreach ($serviceIds as $serviceId) {
            $service = $this->resolveServiceDefinition($builder, $serviceId);

            if ($showHidden xor '.' === ($serviceId[0] ?? null)) {
                continue;
            }

            $serviceXML = $this->getContainerServiceDocument($service, $serviceId, null, $showArguments);
            $containerXML->appendChild($containerXML->ownerDocument->importNode($serviceXML->childNodes->item(0), true));
        }

        return $dom;
    }

    private function getContainerDefinitionDocument(Definition $definition, string $id = null, bool $omitTags = false, bool $showArguments = false, ContainerBuilder $builder = null): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($serviceXML = $dom->createElement('definition'));

        if ($id) {
            $serviceXML->setAttribute('id', $id);
        }

        if ('' !== $classDescription = $this->getClassDescription((string) $definition->getClass())) {
            $serviceXML->appendChild($descriptionXML = $dom->createElement('description'));
            $descriptionXML->appendChild($dom->createCDATASection($classDescription));
        }

        $serviceXML->setAttribute('class', $definition->getClass() ?? '');

        if ($factory = $definition->getFactory()) {
            $serviceXML->appendChild($factoryXML = $dom->createElement('factory'));

            if (\is_array($factory)) {
                if ($factory[0] instanceof Reference) {
                    $factoryXML->setAttribute('service', (string) $factory[0]);
                } elseif ($factory[0] instanceof Definition) {
                    $factoryXML->setAttribute('service', sprintf('inline factory service (%s)', $factory[0]->getClass() ?? 'not configured'));
                } else {
                    $factoryXML->setAttribute('class', $factory[0]);
                }
                $factoryXML->setAttribute('method', $factory[1]);
            } else {
                $factoryXML->setAttribute('function', $factory);
            }
        }

        $serviceXML->setAttribute('public', $definition->isPublic() && !$definition->isPrivate() ? 'true' : 'false');
        $serviceXML->setAttribute('synthetic', $definition->isSynthetic() ? 'true' : 'false');
        $serviceXML->setAttribute('lazy', $definition->isLazy() ? 'true' : 'false');
        $serviceXML->setAttribute('shared', $definition->isShared() ? 'true' : 'false');
        $serviceXML->setAttribute('abstract', $definition->isAbstract() ? 'true' : 'false');
        $serviceXML->setAttribute('autowired', $definition->isAutowired() ? 'true' : 'false');
        $serviceXML->setAttribute('autoconfigured', $definition->isAutoconfigured() ? 'true' : 'false');
        if ($definition->isDeprecated()) {
            $serviceXML->setAttribute('deprecated', 'true');
            $serviceXML->setAttribute('deprecation_message', $definition->getDeprecation($id)['message']);
        } else {
            $serviceXML->setAttribute('deprecated', 'false');
        }
        $serviceXML->setAttribute('file', $definition->getFile() ?? '');

        $calls = $definition->getMethodCalls();
        if (\count($calls) > 0) {
            $serviceXML->appendChild($callsXML = $dom->createElement('calls'));
            foreach ($calls as $callData) {
                $callsXML->appendChild($callXML = $dom->createElement('call'));
                $callXML->setAttribute('method', $callData[0]);
                if ($callData[2] ?? false) {
                    $callXML->setAttribute('returns-clone', 'true');
                }
            }
        }

        if ($showArguments) {
            foreach ($this->getArgumentNodes($definition->getArguments(), $dom, $builder) as $node) {
                $serviceXML->appendChild($node);
            }
        }

        if (!$omitTags) {
            if ($tags = $this->sortTagsByPriority($definition->getTags())) {
                $serviceXML->appendChild($tagsXML = $dom->createElement('tags'));
                foreach ($tags as $tagName => $tagData) {
                    foreach ($tagData as $parameters) {
                        $tagsXML->appendChild($tagXML = $dom->createElement('tag'));
                        $tagXML->setAttribute('name', $tagName);
                        foreach ($parameters as $name => $value) {
                            $tagXML->appendChild($parameterXML = $dom->createElement('parameter'));
                            $parameterXML->setAttribute('name', $name);
                            $parameterXML->appendChild(new \DOMText($this->formatParameter($value)));
                        }
                    }
                }
            }
        }

        if (null !== $builder && null !== $id) {
            $edges = $this->getServiceEdges($builder, $id);
            if ($edges) {
                $serviceXML->appendChild($usagesXML = $dom->createElement('usages'));
                foreach ($edges as $edge) {
                    $usagesXML->appendChild($usageXML = $dom->createElement('usage'));
                    $usageXML->appendChild(new \DOMText($edge));
                }
            }
        }

        return $dom;
    }

    /**
     * @return \DOMNode[]
     */
    private function getArgumentNodes(array $arguments, \DOMDocument $dom, ContainerBuilder $builder = null): array
    {
        $nodes = [];

        foreach ($arguments as $argumentKey => $argument) {
            $argumentXML = $dom->createElement('argument');

            if (\is_string($argumentKey)) {
                $argumentXML->setAttribute('key', $argumentKey);
            }

            if ($argument instanceof ServiceClosureArgument) {
                $argument = $argument->getValues()[0];
            }

            if ($argument instanceof Reference) {
                $argumentXML->setAttribute('type', 'service');
                $argumentXML->setAttribute('id', (string) $argument);
            } elseif ($argument instanceof IteratorArgument || $argument instanceof ServiceLocatorArgument) {
                $argumentXML->setAttribute('type', $argument instanceof IteratorArgument ? 'iterator' : 'service_locator');

                foreach ($this->getArgumentNodes($argument->getValues(), $dom, $builder) as $childArgumentXML) {
                    $argumentXML->appendChild($childArgumentXML);
                }
            } elseif ($argument instanceof Definition) {
                $argumentXML->appendChild($dom->importNode($this->getContainerDefinitionDocument($argument, null, false, true, $builder)->childNodes->item(0), true));
            } elseif ($argument instanceof AbstractArgument) {
                $argumentXML->setAttribute('type', 'abstract');
                $argumentXML->appendChild(new \DOMText($argument->getText()));
            } elseif (\is_array($argument)) {
                $argumentXML->setAttribute('type', 'collection');

                foreach ($this->getArgumentNodes($argument, $dom, $builder) as $childArgumentXML) {
                    $argumentXML->appendChild($childArgumentXML);
                }
            } elseif ($argument instanceof \UnitEnum) {
                $argumentXML->setAttribute('type', 'constant');
                $argumentXML->appendChild(new \DOMText(ltrim(var_export($argument, true), '\\')));
            } else {
                $argumentXML->appendChild(new \DOMText($argument));
            }

            $nodes[] = $argumentXML;
        }

        return $nodes;
    }

    private function getContainerAliasDocument(Alias $alias, string $id = null): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($aliasXML = $dom->createElement('alias'));

        if ($id) {
            $aliasXML->setAttribute('id', $id);
        }

        $aliasXML->setAttribute('service', (string) $alias);
        $aliasXML->setAttribute('public', $alias->isPublic() && !$alias->isPrivate() ? 'true' : 'false');

        return $dom;
    }

    private function getContainerParameterDocument(mixed $parameter, array $options = []): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($parameterXML = $dom->createElement('parameter'));

        if (isset($options['parameter'])) {
            $parameterXML->setAttribute('key', $options['parameter']);
        }

        $parameterXML->appendChild(new \DOMText($this->formatParameter($parameter)));

        return $dom;
    }

    private function getEventDispatcherListenersDocument(EventDispatcherInterface $eventDispatcher, array $options): \DOMDocument
    {
        $event = \array_key_exists('event', $options) ? $options['event'] : null;
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($eventDispatcherXML = $dom->createElement('event-dispatcher'));

        if (null !== $event) {
            $registeredListeners = $eventDispatcher->getListeners($event);
            $this->appendEventListenerDocument($eventDispatcher, $event, $eventDispatcherXML, $registeredListeners);
        } else {
            // Try to see if "events" exists
            $registeredListeners = \array_key_exists('events', $options) ? array_combine($options['events'], array_map(fn ($event) => $eventDispatcher->getListeners($event), $options['events'])) : $eventDispatcher->getListeners();
            ksort($registeredListeners);

            foreach ($registeredListeners as $eventListened => $eventListeners) {
                $eventDispatcherXML->appendChild($eventXML = $dom->createElement('event'));
                $eventXML->setAttribute('name', $eventListened);

                $this->appendEventListenerDocument($eventDispatcher, $eventListened, $eventXML, $eventListeners);
            }
        }

        return $dom;
    }

    private function appendEventListenerDocument(EventDispatcherInterface $eventDispatcher, string $event, \DOMElement $element, array $eventListeners): void
    {
        foreach ($eventListeners as $listener) {
            $callableXML = $this->getCallableDocument($listener);
            $callableXML->childNodes->item(0)->setAttribute('priority', $eventDispatcher->getListenerPriority($event, $listener));

            $element->appendChild($element->ownerDocument->importNode($callableXML->childNodes->item(0), true));
        }
    }

    private function getCallableDocument(mixed $callable): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($callableXML = $dom->createElement('callable'));

        if (\is_array($callable)) {
            $callableXML->setAttribute('type', 'function');

            if (\is_object($callable[0])) {
                $callableXML->setAttribute('name', $callable[1]);
                $callableXML->setAttribute('class', $callable[0]::class);
            } else {
                if (!str_starts_with($callable[1], 'parent::')) {
                    $callableXML->setAttribute('name', $callable[1]);
                    $callableXML->setAttribute('class', $callable[0]);
                    $callableXML->setAttribute('static', 'true');
                } else {
                    $callableXML->setAttribute('name', substr($callable[1], 8));
                    $callableXML->setAttribute('class', $callable[0]);
                    $callableXML->setAttribute('static', 'true');
                    $callableXML->setAttribute('parent', 'true');
                }
            }

            return $dom;
        }

        if (\is_string($callable)) {
            $callableXML->setAttribute('type', 'function');

            if (!str_contains($callable, '::')) {
                $callableXML->setAttribute('name', $callable);
            } else {
                $callableParts = explode('::', $callable);

                $callableXML->setAttribute('name', $callableParts[1]);
                $callableXML->setAttribute('class', $callableParts[0]);
                $callableXML->setAttribute('static', 'true');
            }

            return $dom;
        }

        if ($callable instanceof \Closure) {
            $callableXML->setAttribute('type', 'closure');

            $r = new \ReflectionFunction($callable);
            if (str_contains($r->name, '{closure}')) {
                return $dom;
            }
            $callableXML->setAttribute('name', $r->name);

            if ($class = \PHP_VERSION_ID >= 80111 ? $r->getClosureCalledClass() : $r->getClosureScopeClass()) {
                $callableXML->setAttribute('class', $class->name);
                if (!$r->getClosureThis()) {
                    $callableXML->setAttribute('static', 'true');
                }
            }

            return $dom;
        }

        if (method_exists($callable, '__invoke')) {
            $callableXML->setAttribute('type', 'object');
            $callableXML->setAttribute('name', $callable::class);

            return $dom;
        }

        throw new \InvalidArgumentException('Callable is not describable.');
    }
}
