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
        $showHidden = isset($options['show_hidden']) && $options['show_hidden'];
        $data = [];

        foreach ($this->findDefinitionsByTag($builder, $showHidden) as $tag => $definitions) {
            $data[$tag] = [];
            foreach ($definitions as $definition) {
                $data[$tag][] = $this->getContainerDefinitionData($definition, true);
            }
        }

        $this->writeData($data, $options);
    }

    protected function describeContainerService(object $service, array $options = [], ContainerBuilder $builder = null)
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

    protected function describeContainerServices(ContainerBuilder $builder, array $options = [])
    {
        $serviceIds = isset($options['tag']) && $options['tag']
            ? $this->sortTaggedServicesByPriority($builder->findTaggedServiceIds($options['tag']))
            : $this->sortServiceIds($builder->getServiceIds());
        $showHidden = isset($options['show_hidden']) && $options['show_hidden'];
        $omitTags = isset($options['omit_tags']) && $options['omit_tags'];
        $showArguments = isset($options['show_arguments']) && $options['show_arguments'];
        $data = ['definitions' => [], 'aliases' => [], 'services' => []];

        if (isset($options['filter'])) {
            $serviceIds = array_filter($serviceIds, $options['filter']);
        }

        foreach ($serviceIds as $serviceId) {
            $service = $this->resolveServiceDefinition($builder, $serviceId);

            if ($showHidden xor '.' === ($serviceId[0] ?? null)) {
                continue;
            }

            if ($service instanceof Alias) {
                $data['aliases'][$serviceId] = $this->getContainerAliasData($service);
            } elseif ($service instanceof Definition) {
                $data['definitions'][$serviceId] = $this->getContainerDefinitionData($service, $omitTags, $showArguments);
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

    protected function describeEventDispatcherListeners(EventDispatcherInterface $eventDispatcher, array $options = [])
    {
        $this->writeData($this->getEventDispatcherListenersData($eventDispatcher, $options), $options);
    }

    protected function describeCallable($callable, array $options = [])
    {
        $this->writeData($this->getCallableData($callable), $options);
    }

    protected function describeContainerParameter($parameter, array $options = [])
    {
        $key = $options['parameter'] ?? '';

        $this->writeData([$key => $parameter], $options);
    }

    protected function describeContainerEnvVars(array $envs, array $options = [])
    {
        throw new LogicException('Using the JSON format to debug environment variables is not supported.');
    }

    protected function describeContainerDeprecations(ContainerBuilder $builder, array $options = []): void
    {
        $containerDeprecationFilePath = sprintf('%s/%sDeprecations.log', $builder->getParameter('kernel.build_dir'), $builder->getParameter('kernel.container_class'));
        if (!file_exists($containerDeprecationFilePath)) {
            throw new RuntimeException('The deprecation file does not exist, please try warming the cache first.');
        }

        $logs = unserialize(file_get_contents($containerDeprecationFilePath));

        $formattedLogs = [];
        $remainingCount = 0;
        foreach ($logs as $log) {
            $formattedLogs[] = [
                'message' => $log['message'],
                'file' => $log['file'],
                'line' => $log['line'],
                'count' => $log['count'],
            ];
            $remainingCount += $log['count'];
        }

        $this->writeData(['remainingCount' => $remainingCount, 'deprecations' => $formattedLogs], $options);
    }

    private function writeData(array $data, array $options)
    {
        $flags = $options['json_encoding'] ?? 0;

        // Recursively search for enum values, so we can replace it
        // before json_encode (which will not display anything for \UnitEnum otherwise)
        array_walk_recursive($data, static function (&$value) {
            if ($value instanceof \UnitEnum) {
                $value = ltrim(var_export($value, true), '\\');
            }
        });

        $this->write(json_encode($data, $flags | \JSON_PRETTY_PRINT)."\n");
    }

    protected function getRouteData(Route $route): array
    {
        $data = [
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

        if ('' !== $route->getCondition()) {
            $data['condition'] = $route->getCondition();
        }

        return $data;
    }

    private function getContainerDefinitionData(Definition $definition, bool $omitTags = false, bool $showArguments = false): array
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

        if ('' !== $classDescription = $this->getClassDescription((string) $definition->getClass())) {
            $data['description'] = $classDescription;
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
                    $data['factory_service'] = sprintf('inline factory service (%s)', $factory[0]->getClass() ?? 'class not configured');
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
            foreach ($this->sortTagsByPriority($definition->getTags()) as $tagName => $tagData) {
                foreach ($tagData as $parameters) {
                    $data['tags'][] = ['name' => $tagName, 'parameters' => $parameters];
                }
            }
        }

        return $data;
    }

    private function getContainerAliasData(Alias $alias): array
    {
        return [
            'service' => (string) $alias,
            'public' => $alias->isPublic() && !$alias->isPrivate(),
        ];
    }

    private function getEventDispatcherListenersData(EventDispatcherInterface $eventDispatcher, array $options): array
    {
        $data = [];
        $event = \array_key_exists('event', $options) ? $options['event'] : null;

        if (null !== $event) {
            foreach ($eventDispatcher->getListeners($event) as $listener) {
                $l = $this->getCallableData($listener);
                $l['priority'] = $eventDispatcher->getListenerPriority($event, $listener);
                $data[] = $l;
            }
        } else {
            $registeredListeners = \array_key_exists('events', $options) ? array_combine($options['events'], array_map(function ($event) use ($eventDispatcher) { return $eventDispatcher->getListeners($event); }, $options['events'])) : $eventDispatcher->getListeners();
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

    private function getCallableData($callable): array
    {
        $data = [];

        if (\is_array($callable)) {
            $data['type'] = 'function';

            if (\is_object($callable[0])) {
                $data['name'] = $callable[1];
                $data['class'] = \get_class($callable[0]);
            } else {
                if (!str_starts_with($callable[1], 'parent::')) {
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

            if (!str_contains($callable, '::')) {
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
            if (str_contains($r->name, '{closure}')) {
                return $data;
            }
            $data['name'] = $r->name;

            if ($class = \PHP_VERSION_ID >= 80111 ? $r->getClosureCalledClass() : $r->getClosureScopeClass()) {
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

    private function describeValue($value, bool $omitTags, bool $showArguments)
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

        if ($value instanceof AbstractArgument) {
            return ['type' => 'abstract', 'text' => $value->getText()];
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
