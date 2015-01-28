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

use Symfony\Component\Console\Helper\Table;
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
class TextDescriptor extends Descriptor
{
    /**
     * {@inheritdoc}
     */
    protected function describeRouteCollection(RouteCollection $routes, array $options = array())
    {
        $showControllers = isset($options['show_controllers']) && $options['show_controllers'];
        $headers = array('Name', 'Method', 'Scheme', 'Host', 'Path');

        $table = new Table($this->output);
        $table->setHeaders($showControllers ? array_merge($headers, array('Controller')) : $headers);

        foreach ($routes->all() as $name => $route) {
            $row = array(
                $name,
                $route->getMethods() ? implode('|', $route->getMethods()) : 'ANY',
                $route->getSchemes() ? implode('|', $route->getSchemes()) : 'ANY',
                '' !== $route->getHost() ? $route->getHost() : 'ANY',
                $route->getPath(),
            );

            if ($showControllers) {
                $controller = $route->getDefault('_controller');
                if ($controller instanceof \Closure) {
                    $controller = 'Closure';
                } elseif (is_object($controller)) {
                    $controller = get_class($controller);
                }
                $row[] = $controller;
            }

            $table->addRow($row);
        }

        $this->writeText($this->formatSection('router', 'Current routes')."\n", $options);
        $table->render();
    }

    /**
     * {@inheritdoc}
     */
    protected function describeRoute(Route $route, array $options = array())
    {
        $requirements = $route->getRequirements();
        unset($requirements['_scheme'], $requirements['_method']);

        // fixme: values were originally written as raw
        $description = array(
            '<comment>Path</comment>         '.$route->getPath(),
            '<comment>Path Regex</comment>   '.$route->compile()->getRegex(),
            '<comment>Host</comment>         '.('' !== $route->getHost() ? $route->getHost() : 'ANY'),
            '<comment>Host Regex</comment>   '.('' !== $route->getHost() ? $route->compile()->getHostRegex() : ''),
            '<comment>Scheme</comment>       '.($route->getSchemes() ? implode('|', $route->getSchemes()) : 'ANY'),
            '<comment>Method</comment>       '.($route->getMethods() ? implode('|', $route->getMethods()) : 'ANY'),
            '<comment>Class</comment>        '.get_class($route),
            '<comment>Defaults</comment>     '.$this->formatRouterConfig($route->getDefaults()),
            '<comment>Requirements</comment> '.($requirements ? $this->formatRouterConfig($requirements) : 'NO CUSTOM'),
            '<comment>Options</comment>      '.$this->formatRouterConfig($route->getOptions()),
        );

        if (isset($options['name'])) {
            array_unshift($description, '<comment>Name</comment>         '.$options['name']);
            array_unshift($description, $this->formatSection('router', sprintf('Route "%s"', $options['name'])));
        }

        $this->writeText(implode("\n", $description)."\n", $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerParameters(ParameterBag $parameters, array $options = array())
    {
        $table = new Table($this->output);
        $table->setHeaders(array('Parameter', 'Value'));

        foreach ($this->sortParameters($parameters) as $parameter => $value) {
            $table->addRow(array($parameter, $this->formatParameter($value)));
        }

        $this->writeText($this->formatSection('container', 'List of parameters')."\n", $options);
        $table->render();
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerTags(ContainerBuilder $builder, array $options = array())
    {
        $showPrivate = isset($options['show_private']) && $options['show_private'];
        $description = array($this->formatSection('container', 'Tagged services'));

        foreach ($this->findDefinitionsByTag($builder, $showPrivate) as $tag => $definitions) {
            $description[] = $this->formatSection('tag', $tag);
            $description = array_merge($description, array_keys($definitions));
            $description[] = '';
        }

        $this->writeText(implode("\n", $description), $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerService($service, array $options = array())
    {
        if (!isset($options['id'])) {
            throw new \InvalidArgumentException('An "id" option must be provided.');
        }

        if ($service instanceof Alias) {
            $this->describeContainerAlias($service, $options);
        } elseif ($service instanceof Definition) {
            $this->describeContainerDefinition($service, $options);
        } else {
            $description = $this->formatSection('container', sprintf('Information for service <info>%s</info>', $options['id']))
                ."\n".sprintf('<comment>Service Id</comment>       %s', isset($options['id']) ? $options['id'] : '-')
                ."\n".sprintf('<comment>Class</comment>            %s', get_class($service));

            $this->writeText($description, $options);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerServices(ContainerBuilder $builder, array $options = array())
    {
        $showPrivate = isset($options['show_private']) && $options['show_private'];
        $showTag = isset($options['tag']) ? $options['tag'] : null;

        if ($showPrivate) {
            $label = '<comment>Public</comment> and <comment>private</comment> services';
        } else {
            $label = '<comment>Public</comment> services';
        }

        if ($showTag) {
            $label .= ' with tag <info>'.$options['tag'].'</info>';
        }

        $this->writeText($this->formatSection('container', $label)."\n", $options);

        $serviceIds = isset($options['tag']) && $options['tag'] ? array_keys($builder->findTaggedServiceIds($options['tag'])) : $builder->getServiceIds();
        $maxTags = array();

        foreach ($serviceIds as $key =>  $serviceId) {
            $definition = $this->resolveServiceDefinition($builder, $serviceId);
            if ($definition instanceof Definition) {
                // filter out private services unless shown explicitly
                if (!$showPrivate && !$definition->isPublic()) {
                    unset($serviceIds[$key]);
                    continue;
                }
                if ($showTag) {
                    $tags = $definition->getTag($showTag);
                    foreach ($tags as $tag) {
                        foreach ($tag as $key => $value) {
                            if (!isset($maxTags[$key])) {
                                $maxTags[$key] = strlen($key);
                            }
                            if (strlen($value) > $maxTags[$key]) {
                                $maxTags[$key] = strlen($value);
                            }
                        }
                    }
                }
            }
        }

        $tagsCount = count($maxTags);
        $tagsNames = array_keys($maxTags);

        $table = new Table($this->output);
        $table->setHeaders(array_merge(array('Service ID'), $tagsNames, array('Class name')));

        foreach ($this->sortServiceIds($serviceIds) as $serviceId) {
            $definition = $this->resolveServiceDefinition($builder, $serviceId);
            if ($definition instanceof Definition) {
                if ($showTag) {
                    foreach ($definition->getTag($showTag) as $key => $tag) {
                        $tagValues = array();
                        foreach ($tagsNames as $tagName) {
                            $tagValues[] = isset($tag[$tagName]) ? $tag[$tagName] : "";
                        }
                        if (0 === $key) {
                            $table->addRow(array_merge(array($serviceId), $tagValues, array($definition->getClass())));
                        } else {
                            $table->addRow(array_merge(array('  "'), $tagValues, array('')));
                        }
                    }
                } else {
                    $table->addRow(array($serviceId, $definition->getClass()));
                }
            } elseif ($definition instanceof Alias) {
                $alias = $definition;
                $table->addRow(array_merge(array($serviceId, sprintf('alias for "%s"', $alias)), $tagsCount ? array_fill(0, $tagsCount, "") : array()));
            } else {
                // we have no information (happens with "service_container")
                $table->addRow(array_merge(array($serviceId, get_class($definition)), $tagsCount ? array_fill(0, $tagsCount, "") : array()));
            }
        }

        $table->render();
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerDefinition(Definition $definition, array $options = array())
    {
        $description = isset($options['id'])
            ? array($this->formatSection('container', sprintf('Information for service <info>%s</info>', $options['id'])))
            : array();

        $description[] = sprintf('<comment>Service Id</comment>       %s', isset($options['id']) ? $options['id'] : '-');
        $description[] = sprintf('<comment>Class</comment>            %s', $definition->getClass() ?: "-");

        $tags = $definition->getTags();
        if (count($tags)) {
            $description[] = '<comment>Tags</comment>';
            foreach ($tags as $tagName => $tagData) {
                foreach ($tagData as $parameters) {
                    $description[] = sprintf('    - %-30s (%s)', $tagName, implode(', ', array_map(function ($key, $value) {
                        return sprintf('<info>%s</info>: %s', $key, $value);
                    }, array_keys($parameters), array_values($parameters))));
                }
            }
        } else {
            $description[] = '<comment>Tags</comment>             -';
        }

        $description[] = sprintf('<comment>Scope</comment>            %s', $definition->getScope());
        $description[] = sprintf('<comment>Public</comment>           %s', $definition->isPublic() ? 'yes' : 'no');
        $description[] = sprintf('<comment>Synthetic</comment>        %s', $definition->isSynthetic() ? 'yes' : 'no');
        $description[] = sprintf('<comment>Lazy</comment>             %s', $definition->isLazy() ? 'yes' : 'no');
        if (method_exists($definition, 'isSynchronized')) {
            $description[] = sprintf('<comment>Synchronized</comment>     %s', $definition->isSynchronized(false) ? 'yes' : 'no');
        }
        $description[] = sprintf('<comment>Abstract</comment>         %s', $definition->isAbstract() ? 'yes' : 'no');

        if ($definition->getFile()) {
            $description[] = sprintf('<comment>Required File</comment>    %s', $definition->getFile() ? $definition->getFile() : '-');
        }

        if ($definition->getFactoryClass(false)) {
            $description[] = sprintf('<comment>Factory Class</comment>    %s', $definition->getFactoryClass(false));
        }

        if ($definition->getFactoryService(false)) {
            $description[] = sprintf('<comment>Factory Service</comment>  %s', $definition->getFactoryService(false));
        }

        if ($definition->getFactoryMethod(false)) {
            $description[] = sprintf('<comment>Factory Method</comment>   %s', $definition->getFactoryMethod(false));
        }

        if ($factory = $definition->getFactory()) {
            if (is_array($factory)) {
                if ($factory[0] instanceof Reference) {
                    $description[] = sprintf('<comment>Factory Service</comment>  %s', $factory[0]);
                } elseif ($factory[0] instanceof Definition) {
                    throw new \InvalidArgumentException('Factory is not describable.');
                } else {
                    $description[] = sprintf('<comment>Factory Class</comment>    %s', $factory[0]);
                }
                $description[] = sprintf('<comment>Factory Method</comment>   %s', $factory[1]);
            } else {
                $description[] = sprintf('<comment>Factory Function</comment>    %s', $factory);
            }
        }

        $this->writeText(implode("\n", $description)."\n", $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerAlias(Alias $alias, array $options = array())
    {
        $this->writeText(sprintf("This service is an alias for the service <info>%s</info>\n", (string) $alias), $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerParameter($parameter, array $options = array())
    {
        $this->writeText($this->formatParameter($parameter), $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeEventDispatcherListeners(EventDispatcherInterface $eventDispatcher, array $options = array())
    {
        $event = array_key_exists('event', $options) ? $options['event'] : null;

        $label = 'Registered listeners';
        if (null !== $event) {
            $label .= sprintf(' for event <info>%s</info>', $event);
        } else {
            $label .= ' by event';
        }

        $this->writeText($this->formatSection('event_dispatcher', $label)."\n", $options);

        $registeredListeners = $eventDispatcher->getListeners($event);
        $table = new Table($this->output);

        if (null !== $event) {
            $this->writeText("\n");

            $table->setHeaders(array('Order', 'Callable'));

            foreach ($registeredListeners as $order => $listener) {
                $table->addRow(array(sprintf('#%d', $order + 1), $this->formatCallable($listener)));
            }

            $table->render();
        } else {
            ksort($registeredListeners);
            foreach ($registeredListeners as $eventListened => $eventListeners) {
                $this->writeText(sprintf("\n<info>[Event]</info> %s\n", $eventListened), $options);

                $table->setHeaders(array('Order', 'Callable'));

                foreach ($eventListeners as $order => $eventListener) {
                    $table->addRow(array(sprintf('#%d', $order + 1), $this->formatCallable($eventListener)));
                }

                $table->render();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function describeCallable($callable, array $options = array())
    {
        $this->writeText($this->formatCallable($callable), $options);
    }

    /**
     * @param array $array
     *
     * @return string
     */
    private function formatRouterConfig(array $array)
    {
        if (!count($array)) {
            return 'NONE';
        }

        $string = '';
        ksort($array);
        foreach ($array as $name => $value) {
            $string .= ($string ? "\n".str_repeat(' ', 13) : '').$name.': '.$this->formatValue($value);
        }

        return $string;
    }

    /**
     * @param string $section
     * @param string $message
     *
     * @return string
     */
    private function formatSection($section, $message)
    {
        return sprintf('<info>[%s]</info> %s', $section, $message);
    }

    /**
     * @param callable $callable
     *
     * @return string
     */
    private function formatCallable($callable)
    {
        if (is_array($callable)) {
            if (is_object($callable[0])) {
                return sprintf('%s::%s()', get_class($callable[0]), $callable[1]);
            }

            return sprintf('%s::%s()', $callable[0], $callable[1]);
        }

        if (is_string($callable)) {
            return sprintf('%s()', $callable);
        }

        if ($callable instanceof \Closure) {
            return '\Closure()';
        }

        if (method_exists($callable, '__invoke')) {
            return sprintf('%s::__invoke()', get_class($callable));
        }

        throw new \InvalidArgumentException('Callable is not describable.');
    }

    /**
     * @param string $content
     * @param array  $options
     */
    private function writeText($content, array $options = array())
    {
        $this->write(
            isset($options['raw_text']) && $options['raw_text'] ? strip_tags($content) : $content,
            isset($options['raw_output']) ? !$options['raw_output'] : true
        );
    }
}
