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

use Symfony\Component\DependencyInjection\Alias;
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
    /**
     * {@inheritdoc}
     */
    protected function describeRouteCollection(RouteCollection $routes, array $options = array())
    {
        $this->writeDocument($this->getRouteCollectionDocument($routes));
    }

    /**
     * {@inheritdoc}
     */
    protected function describeRoute(Route $route, array $options = array())
    {
        $this->writeDocument($this->getRouteDocument($route, isset($options['name']) ? $options['name'] : null));
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerParameters(ParameterBag $parameters, array $options = array())
    {
        $this->writeDocument($this->getContainerParametersDocument($parameters));
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerTags(ContainerBuilder $builder, array $options = array())
    {
        $this->writeDocument($this->getContainerTagsDocument($builder, isset($options['show_private']) && $options['show_private']));
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerService($service, array $options = array())
    {
        if (!isset($options['id'])) {
            throw new \InvalidArgumentException('An "id" option must be provided.');
        }

        $this->writeDocument($this->getContainerServiceDocument($service, $options['id']));
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerServices(ContainerBuilder $builder, array $options = array())
    {
        $this->writeDocument($this->getContainerServicesDocument($builder, isset($options['tag']) ? $options['tag'] : null, isset($options['show_private']) && $options['show_private']));
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerDefinition(Definition $definition, array $options = array())
    {
        $this->writeDocument($this->getContainerDefinitionDocument($definition, isset($options['id']) ? $options['id'] : null, isset($options['omit_tags']) && $options['omit_tags']));
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerAlias(Alias $alias, array $options = array())
    {
        $this->writeDocument($this->getContainerAliasDocument($alias, isset($options['id']) ? $options['id'] : null));
    }

    /**
     * {@inheritdoc}
     */
    protected function describeEventDispatcherListeners(EventDispatcherInterface $eventDispatcher, array $options = array())
    {
        $this->writeDocument($this->getEventDispatcherListenersDocument($eventDispatcher, array_key_exists('event', $options) ? $options['event'] : null));
    }

    /**
     * {@inheritdoc}
     */
    protected function describeCallable($callable, array $options = array())
    {
        $this->writeDocument($this->getCallableDocument($callable));
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerParameter($parameter, array $options = array())
    {
        $this->writeDocument($this->getContainerParameterDocument($parameter, $options));
    }

    /**
     * Writes DOM document.
     *
     * @param \DOMDocument $dom
     *
     * @return \DOMDocument|string
     */
    private function writeDocument(\DOMDocument $dom)
    {
        $dom->formatOutput = true;
        $this->write($dom->saveXML());
    }

    /**
     * @param RouteCollection $routes
     *
     * @return \DOMDocument
     */
    private function getRouteCollectionDocument(RouteCollection $routes)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($routesXML = $dom->createElement('routes'));

        foreach ($routes->all() as $name => $route) {
            $routeXML = $this->getRouteDocument($route, $name);
            $routesXML->appendChild($routesXML->ownerDocument->importNode($routeXML->childNodes->item(0), true));
        }

        return $dom;
    }

    /**
     * @param Route       $route
     * @param string|null $name
     *
     * @return \DOMDocument
     */
    private function getRouteDocument(Route $route, $name = null)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($routeXML = $dom->createElement('route'));

        if ($name) {
            $routeXML->setAttribute('name', $name);
        }

        $routeXML->setAttribute('class', get_class($route));

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

        if (count($route->getDefaults())) {
            $routeXML->appendChild($defaultsXML = $dom->createElement('defaults'));
            foreach ($route->getDefaults() as $attribute => $value) {
                $defaultsXML->appendChild($defaultXML = $dom->createElement('default'));
                $defaultXML->setAttribute('key', $attribute);
                $defaultXML->appendChild(new \DOMText($this->formatValue($value)));
            }
        }

        if (count($route->getRequirements())) {
            $routeXML->appendChild($requirementsXML = $dom->createElement('requirements'));
            foreach ($route->getRequirements() as $attribute => $pattern) {
                $requirementsXML->appendChild($requirementXML = $dom->createElement('requirement'));
                $requirementXML->setAttribute('key', $attribute);
                $requirementXML->appendChild(new \DOMText($pattern));
            }
        }

        if (count($route->getOptions())) {
            $routeXML->appendChild($optionsXML = $dom->createElement('options'));
            foreach ($route->getOptions() as $name => $value) {
                $optionsXML->appendChild($optionXML = $dom->createElement('option'));
                $optionXML->setAttribute('key', $name);
                $optionXML->appendChild(new \DOMText($this->formatValue($value)));
            }
        }

        return $dom;
    }

    /**
     * @param ParameterBag $parameters
     *
     * @return \DOMDocument
     */
    private function getContainerParametersDocument(ParameterBag $parameters)
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

    /**
     * @param ContainerBuilder $builder
     * @param bool             $showPrivate
     *
     * @return \DOMDocument
     */
    private function getContainerTagsDocument(ContainerBuilder $builder, $showPrivate = false)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($containerXML = $dom->createElement('container'));

        foreach ($this->findDefinitionsByTag($builder, $showPrivate) as $tag => $definitions) {
            $containerXML->appendChild($tagXML = $dom->createElement('tag'));
            $tagXML->setAttribute('name', $tag);

            foreach ($definitions as $serviceId => $definition) {
                $definitionXML = $this->getContainerDefinitionDocument($definition, $serviceId, true);
                $tagXML->appendChild($dom->importNode($definitionXML->childNodes->item(0), true));
            }
        }

        return $dom;
    }

    /**
     * @param mixed  $service
     * @param string $id
     *
     * @return \DOMDocument
     */
    private function getContainerServiceDocument($service, $id)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');

        if ($service instanceof Alias) {
            $dom->appendChild($dom->importNode($this->getContainerAliasDocument($service, $id)->childNodes->item(0), true));
        } elseif ($service instanceof Definition) {
            $dom->appendChild($dom->importNode($this->getContainerDefinitionDocument($service, $id)->childNodes->item(0), true));
        } else {
            $dom->appendChild($serviceXML = $dom->createElement('service'));
            $serviceXML->setAttribute('id', $id);
            $serviceXML->setAttribute('class', get_class($service));
        }

        return $dom;
    }

    /**
     * @param ContainerBuilder $builder
     * @param string|null      $tag
     * @param bool             $showPrivate
     *
     * @return \DOMDocument
     */
    private function getContainerServicesDocument(ContainerBuilder $builder, $tag = null, $showPrivate = false)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($containerXML = $dom->createElement('container'));

        $serviceIds = $tag ? array_keys($builder->findTaggedServiceIds($tag)) : $builder->getServiceIds();

        foreach ($this->sortServiceIds($serviceIds) as $serviceId) {
            $service = $this->resolveServiceDefinition($builder, $serviceId);

            if ($service instanceof Definition && !($showPrivate || $service->isPublic())) {
                continue;
            }

            $serviceXML = $this->getContainerServiceDocument($service, $serviceId);
            $containerXML->appendChild($containerXML->ownerDocument->importNode($serviceXML->childNodes->item(0), true));
        }

        return $dom;
    }

    /**
     * @param Definition  $definition
     * @param string|null $id
     * @param bool        $omitTags
     *
     * @return \DOMDocument
     */
    private function getContainerDefinitionDocument(Definition $definition, $id = null, $omitTags = false)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($serviceXML = $dom->createElement('definition'));

        if ($id) {
            $serviceXML->setAttribute('id', $id);
        }

        $serviceXML->setAttribute('class', $definition->getClass());

        if ($factory = $definition->getFactory()) {
            $serviceXML->appendChild($factoryXML = $dom->createElement('factory'));

            if (is_array($factory)) {
                if ($factory[0] instanceof Reference) {
                    $factoryXML->setAttribute('service', (string) $factory[0]);
                } elseif ($factory[0] instanceof Definition) {
                    throw new \InvalidArgumentException('Factory is not describable.');
                } else {
                    $factoryXML->setAttribute('class', $factory[0]);
                }
                $factoryXML->setAttribute('method', $factory[1]);
            } else {
                $factoryXML->setAttribute('function', $factory);
            }
        }

        $serviceXML->setAttribute('scope', $definition->getScope());
        $serviceXML->setAttribute('public', $definition->isPublic() ? 'true' : 'false');
        $serviceXML->setAttribute('synthetic', $definition->isSynthetic() ? 'true' : 'false');
        $serviceXML->setAttribute('lazy', $definition->isLazy() ? 'true' : 'false');
        $serviceXML->setAttribute('abstract', $definition->isAbstract() ? 'true' : 'false');
        $serviceXML->setAttribute('file', $definition->getFile());

        if (!$omitTags) {
            $tags = $definition->getTags();

            if (count($tags) > 0) {
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

        return $dom;
    }

    /**
     * @param Alias       $alias
     * @param string|null $id
     *
     * @return \DOMDocument
     */
    private function getContainerAliasDocument(Alias $alias, $id = null)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($aliasXML = $dom->createElement('alias'));

        if ($id) {
            $aliasXML->setAttribute('id', $id);
        }

        $aliasXML->setAttribute('service', (string) $alias);
        $aliasXML->setAttribute('public', $alias->isPublic() ? 'true' : 'false');

        return $dom;
    }

    /**
     * @param string $parameter
     * @param array  $options
     *
     * @return \DOMDocument
     */
    private function getContainerParameterDocument($parameter, $options = array())
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($parameterXML = $dom->createElement('parameter'));

        if (isset($options['parameter'])) {
            $parameterXML->setAttribute('key', $options['parameter']);
        }

        $parameterXML->appendChild(new \DOMText($this->formatParameter($parameter)));

        return $dom;
    }

    /**
     * @param EventDispatcherInterface $eventDispatcher
     * @param string|null              $event
     *
     * @return \DOMDocument
     */
    private function getEventDispatcherListenersDocument(EventDispatcherInterface $eventDispatcher, $event = null)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($eventDispatcherXML = $dom->createElement('event-dispatcher'));

        $registeredListeners = $eventDispatcher->getListeners($event, true);
        if (null !== $event) {
            $this->appendEventListenerDocument($eventDispatcherXML, $registeredListeners);
        } else {
            ksort($registeredListeners);

            foreach ($registeredListeners as $eventListened => $eventListeners) {
                $eventDispatcherXML->appendChild($eventXML = $dom->createElement('event'));
                $eventXML->setAttribute('name', $eventListened);

                $this->appendEventListenerDocument($eventXML, $eventListeners);
            }
        }

        return $dom;
    }

    /**
     * @param DOMElement $element
     * @param array      $eventListeners
     */
    private function appendEventListenerDocument(\DOMElement $element, array $eventListeners)
    {
        krsort($eventListeners);
        foreach ($eventListeners as $priority => $listeners) {
            foreach ($listeners as $listener) {
                $callableXML = $this->getCallableDocument($listener);
                $callableXML->childNodes->item(0)->setAttribute('priority', $priority);

                $element->appendChild($element->ownerDocument->importNode($callableXML->childNodes->item(0), true));
            }
        }
    }

    /**
     * @param callable $callable
     *
     * @return \DOMDocument
     */
    private function getCallableDocument($callable)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($callableXML = $dom->createElement('callable'));

        if (is_array($callable)) {
            $callableXML->setAttribute('type', 'function');

            if (is_object($callable[0])) {
                $callableXML->setAttribute('name', $callable[1]);
                $callableXML->setAttribute('class', get_class($callable[0]));
            } else {
                if (0 !== strpos($callable[1], 'parent::')) {
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

        if (is_string($callable)) {
            $callableXML->setAttribute('type', 'function');

            if (false === strpos($callable, '::')) {
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

            return $dom;
        }

        if (method_exists($callable, '__invoke')) {
            $callableXML->setAttribute('type', 'object');
            $callableXML->setAttribute('name', get_class($callable));

            return $dom;
        }

        throw new \InvalidArgumentException('Callable is not describable.');
    }
}
