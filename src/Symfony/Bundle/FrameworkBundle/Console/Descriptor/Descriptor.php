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

use Symfony\Component\Config\Resource\ClassExistenceResource;
use Symfony\Component\Console\Descriptor\DescriptorInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\AnalyzeServiceReferencesPass;
use Symfony\Component\DependencyInjection\Compiler\ServiceReferenceGraphEdge;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
abstract class Descriptor implements DescriptorInterface
{
    /**
     * @var OutputInterface
     */
    protected $output;

    public function describe(OutputInterface $output, mixed $object, array $options = [])
    {
        $this->output = $output;

        if ($object instanceof ContainerBuilder) {
            (new AnalyzeServiceReferencesPass(false, false))->process($object);
        }

        match (true) {
            $object instanceof RouteCollection => $this->describeRouteCollection($object, $options),
            $object instanceof Route => $this->describeRoute($object, $options),
            $object instanceof ParameterBag => $this->describeContainerParameters($object, $options),
            $object instanceof ContainerBuilder && !empty($options['env-vars']) => $this->describeContainerEnvVars($this->getContainerEnvVars($object), $options),
            $object instanceof ContainerBuilder && isset($options['group_by']) && 'tags' === $options['group_by'] => $this->describeContainerTags($object, $options),
            $object instanceof ContainerBuilder && isset($options['id']) => $this->describeContainerService($this->resolveServiceDefinition($object, $options['id']), $options, $object),
            $object instanceof ContainerBuilder && isset($options['parameter']) => $this->describeContainerParameter($object->resolveEnvPlaceholders($object->getParameter($options['parameter'])), $options),
            $object instanceof ContainerBuilder && isset($options['deprecations']) => $this->describeContainerDeprecations($object, $options),
            $object instanceof ContainerBuilder => $this->describeContainerServices($object, $options),
            $object instanceof Definition => $this->describeContainerDefinition($object, $options),
            $object instanceof Alias => $this->describeContainerAlias($object, $options),
            $object instanceof EventDispatcherInterface => $this->describeEventDispatcherListeners($object, $options),
            \is_callable($object) => $this->describeCallable($object, $options),
            default => throw new \InvalidArgumentException(sprintf('Object of type "%s" is not describable.', get_debug_type($object))),
        };

        if ($object instanceof ContainerBuilder) {
            $object->getCompiler()->getServiceReferenceGraph()->clear();
        }
    }

    protected function getOutput(): OutputInterface
    {
        return $this->output;
    }

    protected function write(string $content, bool $decorated = false)
    {
        $this->output->write($content, false, $decorated ? OutputInterface::OUTPUT_NORMAL : OutputInterface::OUTPUT_RAW);
    }

    abstract protected function describeRouteCollection(RouteCollection $routes, array $options = []);

    abstract protected function describeRoute(Route $route, array $options = []);

    abstract protected function describeContainerParameters(ParameterBag $parameters, array $options = []);

    abstract protected function describeContainerTags(ContainerBuilder $builder, array $options = []);

    /**
     * Describes a container service by its name.
     *
     * Common options are:
     * * name: name of described service
     *
     * @param Definition|Alias|object $service
     */
    abstract protected function describeContainerService(object $service, array $options = [], ContainerBuilder $builder = null);

    /**
     * Describes container services.
     *
     * Common options are:
     * * tag: filters described services by given tag
     */
    abstract protected function describeContainerServices(ContainerBuilder $builder, array $options = []);

    abstract protected function describeContainerDeprecations(ContainerBuilder $builder, array $options = []): void;

    abstract protected function describeContainerDefinition(Definition $definition, array $options = [], ContainerBuilder $builder = null);

    abstract protected function describeContainerAlias(Alias $alias, array $options = [], ContainerBuilder $builder = null);

    abstract protected function describeContainerParameter(mixed $parameter, array $options = []);

    abstract protected function describeContainerEnvVars(array $envs, array $options = []);

    /**
     * Describes event dispatcher listeners.
     *
     * Common options are:
     * * name: name of listened event
     */
    abstract protected function describeEventDispatcherListeners(EventDispatcherInterface $eventDispatcher, array $options = []);

    abstract protected function describeCallable(mixed $callable, array $options = []);

    protected function formatValue(mixed $value): string
    {
        if ($value instanceof \UnitEnum) {
            return ltrim(var_export($value, true), '\\');
        }

        if (\is_object($value)) {
            return sprintf('object(%s)', $value::class);
        }

        if (\is_string($value)) {
            return $value;
        }

        return preg_replace("/\n\s*/s", '', var_export($value, true));
    }

    protected function formatParameter(mixed $value): string
    {
        if ($value instanceof \UnitEnum) {
            return ltrim(var_export($value, true), '\\');
        }

        // Recursively search for enum values, so we can replace it
        // before json_encode (which will not display anything for \UnitEnum otherwise)
        if (\is_array($value)) {
            array_walk_recursive($value, static function (&$value) {
                if ($value instanceof \UnitEnum) {
                    $value = ltrim(var_export($value, true), '\\');
                }
            });
        }

        if (\is_bool($value) || \is_array($value) || (null === $value)) {
            $jsonString = json_encode($value);

            if (preg_match('/^(.{60})./us', $jsonString, $matches)) {
                return $matches[1].'...';
            }

            return $jsonString;
        }

        return (string) $value;
    }

    protected function resolveServiceDefinition(ContainerBuilder $builder, string $serviceId): mixed
    {
        if ($builder->hasDefinition($serviceId)) {
            return $builder->getDefinition($serviceId);
        }

        // Some service IDs don't have a Definition, they're aliases
        if ($builder->hasAlias($serviceId)) {
            return $builder->getAlias($serviceId);
        }

        if ('service_container' === $serviceId) {
            return (new Definition(ContainerInterface::class))->setPublic(true)->setSynthetic(true);
        }

        // the service has been injected in some special way, just return the service
        return $builder->get($serviceId);
    }

    protected function findDefinitionsByTag(ContainerBuilder $builder, bool $showHidden): array
    {
        $definitions = [];
        $tags = $builder->findTags();
        asort($tags);

        foreach ($tags as $tag) {
            foreach ($builder->findTaggedServiceIds($tag) as $serviceId => $attributes) {
                $definition = $this->resolveServiceDefinition($builder, $serviceId);

                if ($showHidden xor '.' === ($serviceId[0] ?? null)) {
                    continue;
                }

                if (!isset($definitions[$tag])) {
                    $definitions[$tag] = [];
                }

                $definitions[$tag][$serviceId] = $definition;
            }
        }

        return $definitions;
    }

    protected function sortParameters(ParameterBag $parameters)
    {
        $parameters = $parameters->all();
        ksort($parameters);

        return $parameters;
    }

    protected function sortServiceIds(array $serviceIds)
    {
        asort($serviceIds);

        return $serviceIds;
    }

    protected function sortTaggedServicesByPriority(array $services): array
    {
        $maxPriority = [];
        foreach ($services as $service => $tags) {
            $maxPriority[$service] = \PHP_INT_MIN;
            foreach ($tags as $tag) {
                $currentPriority = $tag['priority'] ?? 0;
                if ($maxPriority[$service] < $currentPriority) {
                    $maxPriority[$service] = $currentPriority;
                }
            }
        }
        uasort($maxPriority, function ($a, $b) {
            return $b <=> $a;
        });

        return array_keys($maxPriority);
    }

    protected function sortTagsByPriority(array $tags): array
    {
        $sortedTags = [];
        foreach ($tags as $tagName => $tag) {
            $sortedTags[$tagName] = $this->sortByPriority($tag);
        }

        return $sortedTags;
    }

    protected function sortByPriority(array $tag): array
    {
        usort($tag, function ($a, $b) {
            return ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0);
        });

        return $tag;
    }

    public static function getClassDescription(string $class, string &$resolvedClass = null): string
    {
        $resolvedClass = $class;
        try {
            $resource = new ClassExistenceResource($class, false);

            // isFresh() will explode ONLY if a parent class/trait does not exist
            $resource->isFresh(0);

            $r = new \ReflectionClass($class);
            $resolvedClass = $r->name;

            if ($docComment = $r->getDocComment()) {
                $docComment = preg_split('#\n\s*\*\s*[\n@]#', substr($docComment, 3, -2), 2)[0];

                return trim(preg_replace('#\s*\n\s*\*\s*#', ' ', $docComment));
            }
        } catch (\ReflectionException) {
        }

        return '';
    }

    private function getContainerEnvVars(ContainerBuilder $container): array
    {
        if (!$container->hasParameter('debug.container.dump')) {
            return [];
        }

        if (!is_file($container->getParameter('debug.container.dump'))) {
            return [];
        }

        $file = file_get_contents($container->getParameter('debug.container.dump'));
        preg_match_all('{%env\(((?:\w++:)*+\w++)\)%}', $file, $envVars);
        $envVars = array_unique($envVars[1]);

        $bag = $container->getParameterBag();
        $getDefaultParameter = function (string $name) {
            return parent::get($name);
        };
        $getDefaultParameter = $getDefaultParameter->bindTo($bag, $bag::class);

        $getEnvReflection = new \ReflectionMethod($container, 'getEnv');

        $envs = [];

        foreach ($envVars as $env) {
            $processor = 'string';
            if (false !== $i = strrpos($name = $env, ':')) {
                $name = substr($env, $i + 1);
                $processor = substr($env, 0, $i);
            }
            $defaultValue = ($hasDefault = $container->hasParameter("env($name)")) ? $getDefaultParameter("env($name)") : null;
            if (false === ($runtimeValue = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name))) {
                $runtimeValue = null;
            }
            $processedValue = ($hasRuntime = null !== $runtimeValue) || $hasDefault ? $getEnvReflection->invoke($container, $env) : null;
            $envs["$name$processor"] = [
                'name' => $name,
                'processor' => $processor,
                'default_available' => $hasDefault,
                'default_value' => $defaultValue,
                'runtime_available' => $hasRuntime,
                'runtime_value' => $runtimeValue,
                'processed_value' => $processedValue,
            ];
        }
        ksort($envs);

        return array_values($envs);
    }

    protected function getServiceEdges(ContainerBuilder $builder, string $serviceId): array
    {
        try {
            return array_values(array_unique(array_map(function (ServiceReferenceGraphEdge $edge) {
                return $edge->getSourceNode()->getId();
            }, $builder->getCompiler()->getServiceReferenceGraph()->getNode($serviceId)->getInEdges())));
        } catch (InvalidArgumentException $exception) {
            return [];
        }
    }
}
