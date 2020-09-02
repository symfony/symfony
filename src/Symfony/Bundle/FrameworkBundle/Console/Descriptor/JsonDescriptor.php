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
use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
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
class JsonDescriptor extends Descriptor
{
    protected function describeRouteCollection(RouteCollection $routes, array $options = [])
    {
        $data = [];
        foreach ($routes->all() as $name => $route) {
            $data[$name] = $this->getRouteData($route);
        }

        $this->writeData($data, $options);
    }

    protected function describeRoute(Route $route, array $options = [])
    {
        $this->writeData($this->getRouteData($route), $options);
    }

    protected function describeContainerParameters(ParameterBag $parameters, array $options = [])
    {
        $this->writeData($this->sortParameters($parameters), $options);
    }

    protected function describeContainerTags(ContainerBuilder $builder, array $options = [])
    {
        $showPrivate = isset($options['show_private']) && $options['show_private'];
        $data = [];

        foreach ($this->findDefinitionsByTag($builder, $showPrivate) as $tag => $definitions) {
            $data[$tag] = [];
            foreach ($definitions as $definition) {
                $data[$tag][] = $this->getContainerDefinitionData($definition, true);
            }
        }

        $this->writeData($data, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerService($service, array $options = [], ContainerBuilder $builder = null)
    {
        if (!isset($options['id'])) {
            throw new \InvalidArgumentException('An "id" option must be provided.');
        }

        if ($service instanceof Alias) {
            $this->describeContainerAlias($service, $options, $builder);
        } elseif ($service instanceof Definition) {
            $this->writeData($this->getContainerDefinitionData($service, isset($options['omit_tags']) && $options['omit_tags'], isset($options['show_arguments']) && $options['show_arguments']), $options);
        } else {
            $this->writeData(\get_class($service), $options);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerServices(ContainerBuilder $builder, array $options = [])
    {
        $serviceIds = isset($options['tag']) && $options['tag'] ? array_keys($builder->findTaggedServiceIds($options['tag'])) : $builder->getServiceIds();
        $showPrivate = isset($options['show_private']) && $options['show_private'];
        $omitTags = isset($options['omit_tags']) && $options['omit_tags'];
        $showArguments = isset($options['show_arguments']) && $options['show_arguments'];
        $data = ['definitions' => [], 'aliases' => [], 'services' => []];

        if (isset($options['filter'])) {
            $serviceIds = array_filter($serviceIds, $options['filter']);
        }

        foreach ($this->sortServiceIds($serviceIds) as $serviceId) {
            $service = $this->resolveServiceDefinition($builder, $serviceId);

            if ($service instanceof Alias) {
                if ($showPrivate || ($service->isPublic() && !$service->isPrivate())) {
                    $data['aliases'][$serviceId] = $this->getContainerAliasData($service);
                }
            } elseif ($service instanceof Definition) {
                if (($showPrivate || ($service->isPublic() && !$service->isPrivate()))) {
                    $data['definitions'][$serviceId] = $this->getContainerDefinitionData($service, $omitTags, $showArguments);
                }
            } else {
                $data['services'][$serviceId] = \get_class($service);
            }
        }

        $this->writeData($data, $options);
    }

    protected function describeContainerDefinition(Definition $definition, array $options = [])
    {
        $this->writeData($this->getContainerDefinitionData($definition, isset($options['omit_tags']) && $options['omit_tags'], isset($options['show_arguments']) && $options['show_arguments']), $options);
    }

    protected function describeContainerAlias(Alias $alias, array $options = [], ContainerBuilder $builder = null)
    {
        if (!$builder) {
            $this->writeData($this->getContainerAliasData($alias), $options);

            return;
        }

        $this->writeData(
            [$this->getContainerAliasData($alias), $this->getContainerDefinitionData($builder->getDefinition((string) $alias), isset($options['omit_tags']) && $options['omit_tags'], isset($options['show_arguments']) && $options['show_arguments'])],
            array_merge($options, ['id' => (string) $alias])
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function describeEventDispatcherListeners(EventDispatcherInterface $eventDispatcher, array $options = [])
    {
        $this->writeData($this->getEventDispatcherListenersData($eventDispatcher, \array_key_exists('event', $options) ? $options['event'] : null), $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeCallable($callable, array $options = [])
    {
        $this->writeData($this->getCallableData($callable), $options);
    }

    protected function describeContainerParameter($parameter, array $options = [])
    {
        $key = isset($options['parameter']) ? $options['parameter'] : '';

        $this->writeData([$key => $parameter], $options);
    }

    /**
     * Writes data as json.
     */
    private function writeData(array $data, array $options)
    {
        $flags = isset($options['json_encoding']) ? $options['json_encoding'] : 0;

        $this->write(json_encode($data, $flags | \JSON_PRETTY_PRINT)."\n");
    }

    /**
     * @return array
     */
    protected function getRouteData(Route $route)
    {
        return [
            'path' => $route->getPath(),
            'pathRegex' => $route->compile()->getRegex(),
            'host' => '' !== $route->getHost() ? $route->getHost() : 'ANY',
            'hostRegex' => '' !== $route->getHost() ? $route->compile()->getHostRegex() : '',
            'scheme' => $route->getSchemes() ? implode('|', $route->getSchemes()) : 'ANY',
            'method' => $route->getMethods() ? implode('|', $route->getMethods()) : 'ANY',
            'class' => \get_class($route),
            'defaults' => $route->getDefaults(),
            'requirements' => $route->getRequirements() ?: 'NO CUSTOM',
            'options' => $route->getOptions(),
        ];
    }

    /**
     * @param bool $omitTags
     *
     * @return array
     */
    private function getContainerDefinitionData(Definition $definition, $omitTags = false, $showArguments = false)
    {
        $data = [
            'class' => (string) $definition->getClass(),
            'public' => $definition->isPublic() && !$definition->isPrivate(),
            'synthetic' => $definition->isSynthetic(),
            'lazy' => $definition->isLazy(),
            'shared' => $definition->isShared(),
            'abstract' => $definition->isAbstract(),
            'autowire' => $definition->isAutowired(),
            'autoconfigure' => $definition->isAutoconfigured(),
        ];

        // forward compatibility with DependencyInjection component in version 4.0
        if (method_exists($definition, 'getAutowiringTypes')) {
            foreach ($definition->getAutowiringTypes(false) as $autowiringType) {
                $data['autowiring_types'][] = $autowiringType;
            }
        }

        if ($showArguments) {
            $data['arguments'] = $this->describeValue($definition->getArguments(), $omitTags, $showArguments);
        }

        $data['file'] = $definition->getFile();

        if ($factory = $definition->getFactory()) {
            if (\is_array($factory)) {
                if ($factory[0] instanceof Reference) {
                    $data['factory_service'] = (string) $factory[0];
                } elseif ($factory[0] instanceof Definition) {
                    throw new \InvalidArgumentException('Factory is not describable.');
                } else {
                    $data['factory_class'] = $factory[0];
                }
                $data['factory_method'] = $factory[1];
            } else {
                $data['factory_function'] = $factory;
            }
        }

        $calls = $definition->getMethodCalls();
        if (\count($calls) > 0) {
            $data['calls'] = [];
            foreach ($calls as $callData) {
                $data['calls'][] = $callData[0];
            }
        }

        if (!$omitTags) {
            $data['tags'] = [];
            foreach ($definition->getTags() as $tagName => $tagData) {
                foreach ($tagData as $parameters) {
                    $data['tags'][] = ['name' => $tagName, 'parameters' => $parameters];
                }
            }
        }

        return $data;
    }

    /**
     * @return array
     */
    private function getContainerAliasData(Alias $alias)
    {
        return [
            'service' => (string) $alias,
            'public' => $alias->isPublic() && !$alias->isPrivate(),
        ];
    }

    /**
     * @param string|null $event
     *
     * @return array
     */
    private function getEventDispatcherListenersData(EventDispatcherInterface $eventDispatcher, $event = null)
    {
        $data = [];

        $registeredListeners = $eventDispatcher->getListeners($event);
        if (null !== $event) {
            foreach ($registeredListeners as $listener) {
                $l = $this->getCallableData($listener);
                $l['priority'] = $eventDispatcher->getListenerPriority($event, $listener);
                $data[] = $l;
            }
        } else {
            ksort($registeredListeners);

            foreach ($registeredListeners as $eventListened => $eventListeners) {
                foreach ($eventListeners as $eventListener) {
                    $l = $this->getCallableData($eventListener);
                    $l['priority'] = $eventDispatcher->getListenerPriority($eventListened, $eventListener);
                    $data[$eventListened][] = $l;
                }
            }
        }

        return $data;
    }

    /**
     * @param callable $callable
     *
     * @return array
     */
    private function getCallableData($callable)
    {
        $data = [];

        if (\is_array($callable)) {
            $data['type'] = 'function';

            if (\is_object($callable[0])) {
                $data['name'] = $callable[1];
                $data['class'] = \get_class($callable[0]);
            } else {
                if (0 !== strpos($callable[1], 'parent::')) {
                    $data['name'] = $callable[1];
                    $data['class'] = $callable[0];
                    $data['static'] = true;
                } else {
                    $data['name'] = substr($callable[1], 8);
                    $data['class'] = $callable[0];
                    $data['static'] = true;
                    $data['parent'] = true;
                }
            }

            return $data;
        }

        if (\is_string($callable)) {
            $data['type'] = 'function';

            if (false === strpos($callable, '::')) {
                $data['name'] = $callable;
            } else {
                $callableParts = explode('::', $callable);

                $data['name'] = $callableParts[1];
                $data['class'] = $callableParts[0];
                $data['static'] = true;
            }

            return $data;
        }

        if ($callable instanceof \Closure) {
            $data['type'] = 'closure';

            $r = new \ReflectionFunction($callable);
            if (false !== strpos($r->name, '{closure}')) {
                return $data;
            }
            $data['name'] = $r->name;

            if ($class = $r->getClosureScopeClass()) {
                $data['class'] = $class->name;
                if (!$r->getClosureThis()) {
                    $data['static'] = true;
                }
            }

            return $data;
        }

        if (method_exists($callable, '__invoke')) {
            $data['type'] = 'object';
            $data['name'] = \get_class($callable);

            return $data;
        }

        throw new \InvalidArgumentException('Callable is not describable.');
    }

    private function describeValue($value, $omitTags, $showArguments)
    {
        if (\is_array($value)) {
            $data = [];
            foreach ($value as $k => $v) {
                $data[$k] = $this->describeValue($v, $omitTags, $showArguments);
            }

            return $data;
        }

        if ($value instanceof ServiceClosureArgument) {
            $value = $value->getValues()[0];
        }

        if ($value instanceof Reference) {
            return [
                'type' => 'service',
                'id' => (string) $value,
            ];
        }

        if ($value instanceof ArgumentInterface) {
            return $this->describeValue($value->getValues(), $omitTags, $showArguments);
        }

        if ($value instanceof Definition) {
            return $this->getContainerDefinitionData($value, $omitTags, $showArguments);
        }

        return $value;
    }
}
