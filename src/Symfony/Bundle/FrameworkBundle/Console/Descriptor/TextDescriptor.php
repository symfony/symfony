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

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
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
class TextDescriptor extends Descriptor
{
    protected function describeRouteCollection(RouteCollection $routes, array $options = [])
    {
        $showControllers = isset($options['show_controllers']) && $options['show_controllers'];

        $tableHeaders = ['Name', 'Method', 'Scheme', 'Host', 'Path'];
        if ($showControllers) {
            $tableHeaders[] = 'Controller';
        }

        $tableRows = [];
        foreach ($routes->all() as $name => $route) {
            $row = [
                $name,
                $route->getMethods() ? implode('|', $route->getMethods()) : 'ANY',
                $route->getSchemes() ? implode('|', $route->getSchemes()) : 'ANY',
                '' !== $route->getHost() ? $route->getHost() : 'ANY',
                $route->getPath(),
            ];

            if ($showControllers) {
                $controller = $route->getDefault('_controller');
                $row[] = $controller ? $this->formatCallable($controller) : '';
            }

            $tableRows[] = $row;
        }

        if (isset($options['output'])) {
            $options['output']->table($tableHeaders, $tableRows);
        } else {
            $table = new Table($this->getOutput());
            $table->setHeaders($tableHeaders)->setRows($tableRows);
            $table->render();
        }
    }

    protected function describeRoute(Route $route, array $options = [])
    {
        $tableHeaders = ['Property', 'Value'];
        $tableRows = [
            ['Route Name', isset($options['name']) ? $options['name'] : ''],
            ['Path', $route->getPath()],
            ['Path Regex', $route->compile()->getRegex()],
            ['Host', ('' !== $route->getHost() ? $route->getHost() : 'ANY')],
            ['Host Regex', ('' !== $route->getHost() ? $route->compile()->getHostRegex() : '')],
            ['Scheme', ($route->getSchemes() ? implode('|', $route->getSchemes()) : 'ANY')],
            ['Method', ($route->getMethods() ? implode('|', $route->getMethods()) : 'ANY')],
            ['Requirements', ($route->getRequirements() ? $this->formatRouterConfig($route->getRequirements()) : 'NO CUSTOM')],
            ['Class', \get_class($route)],
            ['Defaults', $this->formatRouterConfig($route->getDefaults())],
            ['Options', $this->formatRouterConfig($route->getOptions())],
        ];
        if (isset($options['callable'])) {
            $tableRows[] = ['Callable', $options['callable']];
        }

        $table = new Table($this->getOutput());
        $table->setHeaders($tableHeaders)->setRows($tableRows);
        $table->render();
    }

    protected function describeContainerParameters(ParameterBag $parameters, array $options = [])
    {
        $tableHeaders = ['Parameter', 'Value'];

        $tableRows = [];
        foreach ($this->sortParameters($parameters) as $parameter => $value) {
            $tableRows[] = [$parameter, $this->formatParameter($value)];
        }

        $options['output']->title('Symfony Container Parameters');
        $options['output']->table($tableHeaders, $tableRows);
    }

    protected function describeContainerTags(ContainerBuilder $builder, array $options = [])
    {
        $showPrivate = isset($options['show_private']) && $options['show_private'];

        if ($showPrivate) {
            $options['output']->title('Symfony Container Public and Private Tags');
        } else {
            $options['output']->title('Symfony Container Public Tags');
        }

        foreach ($this->findDefinitionsByTag($builder, $showPrivate) as $tag => $definitions) {
            $options['output']->section(sprintf('"%s" tag', $tag));
            $options['output']->listing(array_keys($definitions));
        }
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
            $this->describeContainerDefinition($service, $options);
        } else {
            $options['output']->title(sprintf('Information for Service "<info>%s</info>"', $options['id']));
            $options['output']->table(
                ['Service ID', 'Class'],
                [
                    [isset($options['id']) ? $options['id'] : '-', \get_class($service)],
                ]
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerServices(ContainerBuilder $builder, array $options = [])
    {
        $showPrivate = isset($options['show_private']) && $options['show_private'];
        $showTag = isset($options['tag']) ? $options['tag'] : null;

        if ($showPrivate) {
            $title = 'Symfony Container Public and Private Services';
        } else {
            $title = 'Symfony Container Public Services';
        }

        if ($showTag) {
            $title .= sprintf(' Tagged with "%s" Tag', $options['tag']);
        }

        $options['output']->title($title);

        $serviceIds = isset($options['tag']) && $options['tag'] ? array_keys($builder->findTaggedServiceIds($options['tag'])) : $builder->getServiceIds();
        $maxTags = [];

        if (isset($options['filter'])) {
            $serviceIds = array_filter($serviceIds, $options['filter']);
        }

        foreach ($serviceIds as $key => $serviceId) {
            $definition = $this->resolveServiceDefinition($builder, $serviceId);
            if ($definition instanceof Definition) {
                // filter out private services unless shown explicitly
                if (!$showPrivate && (!$definition->isPublic() || $definition->isPrivate())) {
                    unset($serviceIds[$key]);
                    continue;
                }
                if ($showTag) {
                    $tags = $definition->getTag($showTag);
                    foreach ($tags as $tag) {
                        foreach ($tag as $key => $value) {
                            if (!isset($maxTags[$key])) {
                                $maxTags[$key] = \strlen($key);
                            }
                            if (\strlen($value) > $maxTags[$key]) {
                                $maxTags[$key] = \strlen($value);
                            }
                        }
                    }
                }
            } elseif ($definition instanceof Alias) {
                if (!$showPrivate && (!$definition->isPublic() || $definition->isPrivate())) {
                    unset($serviceIds[$key]);
                    continue;
                }
            }
        }

        $tagsCount = \count($maxTags);
        $tagsNames = array_keys($maxTags);

        $tableHeaders = array_merge(['Service ID'], $tagsNames, ['Class name']);
        $tableRows = [];
        $rawOutput = isset($options['raw_text']) && $options['raw_text'];
        foreach ($this->sortServiceIds($serviceIds) as $serviceId) {
            $definition = $this->resolveServiceDefinition($builder, $serviceId);

            $styledServiceId = $rawOutput ? $serviceId : sprintf('<fg=cyan>%s</fg=cyan>', OutputFormatter::escape($serviceId));
            if ($definition instanceof Definition) {
                if ($showTag) {
                    foreach ($definition->getTag($showTag) as $key => $tag) {
                        $tagValues = [];
                        foreach ($tagsNames as $tagName) {
                            $tagValues[] = isset($tag[$tagName]) ? $tag[$tagName] : '';
                        }
                        if (0 === $key) {
                            $tableRows[] = array_merge([$serviceId], $tagValues, [$definition->getClass()]);
                        } else {
                            $tableRows[] = array_merge(['  "'], $tagValues, ['']);
                        }
                    }
                } else {
                    $tableRows[] = [$styledServiceId, $definition->getClass()];
                }
            } elseif ($definition instanceof Alias) {
                $alias = $definition;
                $tableRows[] = array_merge([$styledServiceId, sprintf('alias for "%s"', $alias)], $tagsCount ? array_fill(0, $tagsCount, '') : []);
            } else {
                $tableRows[] = array_merge([$styledServiceId, \get_class($definition)], $tagsCount ? array_fill(0, $tagsCount, '') : []);
            }
        }

        $options['output']->table($tableHeaders, $tableRows);
    }

    protected function describeContainerDefinition(Definition $definition, array $options = [])
    {
        if (isset($options['id'])) {
            $options['output']->title(sprintf('Information for Service "<info>%s</info>"', $options['id']));
        }

        $tableHeaders = ['Option', 'Value'];

        $tableRows[] = ['Service ID', isset($options['id']) ? $options['id'] : '-'];
        $tableRows[] = ['Class', $definition->getClass() ?: '-'];

        $omitTags = isset($options['omit_tags']) && $options['omit_tags'];
        if (!$omitTags && ($tags = $definition->getTags())) {
            $tagInformation = [];
            foreach ($tags as $tagName => $tagData) {
                foreach ($tagData as $tagParameters) {
                    $parameters = array_map(function ($key, $value) {
                        return sprintf('<info>%s</info>: %s', $key, $value);
                    }, array_keys($tagParameters), array_values($tagParameters));
                    $parameters = implode(', ', $parameters);

                    if ('' === $parameters) {
                        $tagInformation[] = sprintf('%s', $tagName);
                    } else {
                        $tagInformation[] = sprintf('%s (%s)', $tagName, $parameters);
                    }
                }
            }
            $tagInformation = implode("\n", $tagInformation);
        } else {
            $tagInformation = '-';
        }
        $tableRows[] = ['Tags', $tagInformation];

        $calls = $definition->getMethodCalls();
        if (\count($calls) > 0) {
            $callInformation = [];
            foreach ($calls as $call) {
                $callInformation[] = $call[0];
            }
            $tableRows[] = ['Calls', implode(', ', $callInformation)];
        }

        $tableRows[] = ['Public', $definition->isPublic() && !$definition->isPrivate() ? 'yes' : 'no'];
        $tableRows[] = ['Synthetic', $definition->isSynthetic() ? 'yes' : 'no'];
        $tableRows[] = ['Lazy', $definition->isLazy() ? 'yes' : 'no'];
        $tableRows[] = ['Shared', $definition->isShared() ? 'yes' : 'no'];
        $tableRows[] = ['Abstract', $definition->isAbstract() ? 'yes' : 'no'];
        $tableRows[] = ['Autowired', $definition->isAutowired() ? 'yes' : 'no'];
        $tableRows[] = ['Autoconfigured', $definition->isAutoconfigured() ? 'yes' : 'no'];

        // forward compatibility with DependencyInjection component in version 4.0
        if (method_exists($definition, 'getAutowiringTypes') && $autowiringTypes = $definition->getAutowiringTypes(false)) {
            $tableRows[] = ['Autowiring Types', implode(', ', $autowiringTypes)];
        }

        if ($definition->getFile()) {
            $tableRows[] = ['Required File', $definition->getFile() ?: '-'];
        }

        if ($factory = $definition->getFactory()) {
            if (\is_array($factory)) {
                if ($factory[0] instanceof Reference) {
                    $tableRows[] = ['Factory Service', $factory[0]];
                } elseif ($factory[0] instanceof Definition) {
                    throw new \InvalidArgumentException('Factory is not describable.');
                } else {
                    $tableRows[] = ['Factory Class', $factory[0]];
                }
                $tableRows[] = ['Factory Method', $factory[1]];
            } else {
                $tableRows[] = ['Factory Function', $factory];
            }
        }

        $showArguments = isset($options['show_arguments']) && $options['show_arguments'];
        $argumentsInformation = [];
        if ($showArguments && ($arguments = $definition->getArguments())) {
            foreach ($arguments as $argument) {
                if ($argument instanceof ServiceClosureArgument) {
                    $argument = $argument->getValues()[0];
                }
                if ($argument instanceof Reference) {
                    $argumentsInformation[] = sprintf('Service(%s)', (string) $argument);
                } elseif ($argument instanceof IteratorArgument) {
                    if ($argument instanceof TaggedIteratorArgument) {
                        $argumentsInformation[] = sprintf('Tagged Iterator for "%s"%s', $argument->getTag(), $options['is_debug'] ? '' : sprintf(' (%d element(s))', \count($argument->getValues())));
                    } else {
                        $argumentsInformation[] = sprintf('Iterator (%d element(s))', \count($argument->getValues()));
                    }
                } elseif ($argument instanceof Definition) {
                    $argumentsInformation[] = 'Inlined Service';
                } else {
                    $argumentsInformation[] = \is_array($argument) ? sprintf('Array (%d element(s))', \count($argument)) : $argument;
                }
            }

            $tableRows[] = ['Arguments', implode("\n", $argumentsInformation)];
        }

        $options['output']->table($tableHeaders, $tableRows);
    }

    protected function describeContainerAlias(Alias $alias, array $options = [], ContainerBuilder $builder = null)
    {
        $options['output']->comment(sprintf('This service is an alias for the service <info>%s</info>', (string) $alias));

        if (!$builder) {
            return;
        }

        $this->describeContainerDefinition($builder->getDefinition((string) $alias), array_merge($options, ['id' => (string) $alias]));
    }

    protected function describeContainerParameter($parameter, array $options = [])
    {
        $options['output']->table(
            ['Parameter', 'Value'],
            [
                [$options['parameter'], $this->formatParameter($parameter),
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeEventDispatcherListeners(EventDispatcherInterface $eventDispatcher, array $options = [])
    {
        $event = \array_key_exists('event', $options) ? $options['event'] : null;

        if (null !== $event) {
            $title = sprintf('Registered Listeners for "%s" Event', $event);
        } else {
            $title = 'Registered Listeners Grouped by Event';
        }

        $options['output']->title($title);

        $registeredListeners = $eventDispatcher->getListeners($event);
        if (null !== $event) {
            $this->renderEventListenerTable($eventDispatcher, $event, $registeredListeners, $options['output']);
        } else {
            ksort($registeredListeners);
            foreach ($registeredListeners as $eventListened => $eventListeners) {
                $options['output']->section(sprintf('"%s" event', $eventListened));
                $this->renderEventListenerTable($eventDispatcher, $eventListened, $eventListeners, $options['output']);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function describeCallable($callable, array $options = [])
    {
        $this->writeText($this->formatCallable($callable), $options);
    }

    private function renderEventListenerTable(EventDispatcherInterface $eventDispatcher, $event, array $eventListeners, SymfonyStyle $io)
    {
        $tableHeaders = ['Order', 'Callable', 'Priority'];
        $tableRows = [];

        foreach ($eventListeners as $order => $listener) {
            $tableRows[] = [sprintf('#%d', $order + 1), $this->formatCallable($listener), $eventDispatcher->getListenerPriority($event, $listener)];
        }

        $io->table($tableHeaders, $tableRows);
    }

    /**
     * @return string
     */
    private function formatRouterConfig(array $config)
    {
        if (empty($config)) {
            return 'NONE';
        }

        ksort($config);

        $configAsString = '';
        foreach ($config as $key => $value) {
            $configAsString .= sprintf("\n%s: %s", $key, $this->formatValue($value));
        }

        return trim($configAsString);
    }

    /**
     * @param callable $callable
     *
     * @return string
     */
    private function formatCallable($callable)
    {
        if (\is_array($callable)) {
            if (\is_object($callable[0])) {
                return sprintf('%s::%s()', \get_class($callable[0]), $callable[1]);
            }

            return sprintf('%s::%s()', $callable[0], $callable[1]);
        }

        if (\is_string($callable)) {
            return sprintf('%s()', $callable);
        }

        if ($callable instanceof \Closure) {
            $r = new \ReflectionFunction($callable);
            if (false !== strpos($r->name, '{closure}')) {
                return 'Closure()';
            }
            if ($class = $r->getClosureScopeClass()) {
                return sprintf('%s::%s()', $class->name, $r->name);
            }

            return $r->name.'()';
        }

        if (method_exists($callable, '__invoke')) {
            return sprintf('%s::__invoke()', \get_class($callable));
        }

        throw new \InvalidArgumentException('Callable is not describable.');
    }

    /**
     * @param string $content
     */
    private function writeText($content, array $options = [])
    {
        $this->write(
            isset($options['raw_text']) && $options['raw_text'] ? strip_tags($content) : $content,
            isset($options['raw_output']) ? !$options['raw_output'] : true
        );
    }
}
