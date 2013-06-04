<?php

namespace Symfony\Bundle\FrameworkBundle\Console\Descriptor;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class TextDescriptor extends Descriptor
{
    /**
     * {@inheritdoc}
     */
    protected function describeRouteCollection(RouteCollection $routes, array $options = array())
    {
        $maxName = strlen('name');
        $maxMethod = strlen('method');
        $maxScheme = strlen('scheme');
        $maxHost = strlen('host');
        $maxPath = strlen('path');

        foreach ($routes->all() as $name => $route) {
            $method = $route->getMethods() ? implode('|', $route->getMethods()) : 'ANY';
            $scheme = $route->getSchemes() ? implode('|', $route->getSchemes()) : 'ANY';
            $host = '' !== $route->getHost() ? $route->getHost() : 'ANY';
            $path = $route->getPath();
            $maxName = max($maxName, strlen($name));
            $maxMethod = max($maxMethod, strlen($method));
            $maxScheme = max($maxScheme, strlen($scheme));
            $maxHost = max($maxHost, strlen($host));
            $maxPath = max($maxPath, strlen($path));
        }

        $format = '%-'.$maxName.'s %-'.$maxMethod.'s %-'.$maxScheme.'s %-'.$maxHost.'s %s';
        $headerFormat = '%-'.($maxName + 19).'s %-'.($maxMethod + 19).'s %-'.($maxScheme + 19).'s %-'.($maxHost + 19).'s %s';
        $headerArgs = array('<comment>Name</comment>', '<comment>Method</comment>', '<comment>Scheme</comment>', '<comment>Host</comment>', '<comment>Path</comment>');

        if ($showControllers = isset($options['show_controllers']) && $options['show_controllers']) {
            $format = str_replace('s %s', 's %-'.$maxPath.'s %s', $format);
            $headerFormat = $headerFormat.' %s';
            $headerArgs[] = '<comment>Controller</comment>';
        }

        $description = array(
            $this->formatSection('router', 'Current routes'),
            vsprintf($headerFormat, $headerArgs),
        );

        foreach ($routes->all() as $name => $route) {
            $args = array(
                $name,
                $route->getMethods() ? implode('|', $route->getMethods()) : 'ANY',
                $route->getSchemes() ? implode('|', $route->getSchemes()) : 'ANY',
                '' !== $route->getHost() ? $route->getHost() : 'ANY',
                $route->getPath(),
            );

            if ($showControllers) {
                $defaultData = $route->getDefaults();
                $controller = $defaultData['_controller'] ? $defaultData['_controller'] : '';
                if ($controller instanceof \Closure) {
                    $controller = 'Closure';
                } else {
                    if (is_object($controller)) {
                        $controller = get_class($controller);
                    }
                }

                $args[] = $controller;
            }

            // fixme: this line was originally written as raw
            $description[] = vsprintf($format, $args);
        }

        return $this->output(implode("\n", $description), $options);
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
            '<comment>Host</comment>         '.('' !== $route->getHost() ? $route->getHost() : 'ANY'),
            '<comment>Scheme</comment>       '.($route->getSchemes() ? implode('|', $route->getSchemes()) : 'ANY'),
            '<comment>Method</comment>       '.($route->getMethods() ? implode('|', $route->getMethods()) : 'ANY'),
            '<comment>Class</comment>        '.get_class($route),
            '<comment>Defaults</comment>     '.$this->formatRouterConfig($route->getDefaults()),
            '<comment>Requirements</comment> '.$this->formatRouterConfig($requirements) ?: 'NO CUSTOM',
            '<comment>Options</comment>      '.$this->formatRouterConfig($route->getOptions()),
            '<comment>Path-Regex</comment>   '.$route->compile()->getRegex(),
        );

        if (isset($options['name'])) {
            array_unshift($description, '<comment>Name</comment>         '.$options['name']);
            array_unshift($description, $this->formatSection('router', sprintf('Route "%s"', $options['name'])));
        }

        if (null !== $route->compile()->getHostRegex()) {
            $description[] = '<comment>Host-Regex</comment>   '.$route->compile()->getHostRegex();
        }

        return $this->output(implode("\n", $description), $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerParameters(ParameterBag $parameters, array $options = array())
    {
        $maxParameterWidth = 0;
        $maxValueWidth = 0;

        // Determine max parameter & value length
        foreach ($parameters->all() as $parameter => $value) {
            $parameterWidth = strlen($parameter);
            if ($parameterWidth > $maxParameterWidth) {
                $maxParameterWidth = $parameterWidth;
            }

            $valueWith = strlen($this->formatParameter($value));
            if ($valueWith > $maxValueWidth) {
                $maxValueWidth = $valueWith;
            }
        }

        $maxValueWidth = min($maxValueWidth, (isset($options['max_width']) ? $options['max_width'] : PHP_INT_MAX) - $maxParameterWidth - 1);

        $formatTitle = '%-'.($maxParameterWidth + 19).'s %s';
        $format = '%-'.$maxParameterWidth.'s %s';

        $output = array(sprintf($formatTitle, '<comment>Parameter</comment>', '<comment>Value</comment>'));

        foreach ($this->sortParameters($parameters) as $parameter => $value) {
            $splits = str_split($this->formatParameter($value), $maxValueWidth);

            foreach ($splits as $index => $split) {
                if (0 === $index) {
                    $output[] = sprintf($format, $parameter, $split);
                } else {
                    $output[] = sprintf($format, ' ', $split);
                }
            }
        }

        return $this->output($this->formatSection('container', 'List of parameters')."\n".implode("\n", $output));
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

        return $this->output(implode("\n", $description), $options);
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
            return $this->describeContainerAlias($service, $options);
        }

        if ($service instanceof Definition) {
            return $this->describeContainerDefinition($service, $options);
        }

        $description = $this->formatSection('container', sprintf('Information for service <info>%s</info>', $options['id']))
            ."\n".sprintf('<comment>Service Id</comment>       %s', isset($options['id']) ? $options['id'] : '-')
            ."\n".sprintf('<comment>Class</comment>            %s', get_class($service));

        return $this->output($description, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerServices(ContainerBuilder $builder, array $options = array())
    {
        $showPrivate = isset($options['show_private']) && $options['show_private'];
        if ($showPrivate) {
            $label = '<comment>Public</comment> and <comment>private</comment> services';
        } else {
            $label = '<comment>Public</comment> services';
        }

        if (isset($options['tag'])) {
            $label .= ' with tag <info>'.$options['tag'].'</info>';
        }

        $serviceIds = isset($options['tag']) && $options['tag'] ? array_keys($builder->findTaggedServiceIds($options['tag'])) : $builder->getServiceIds();
        $description = $this->describeServices($builder, $serviceIds, $showPrivate, isset($options['tag']) ? $options['tag'] : null);

        return $this->output($this->formatSection('container', $label)."\n".implode("\n", $description), $options);
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
                    $description[] = sprintf('    - %-30s (%s)', $tagName, implode(', ', array_map(function($key, $value) {
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
        $description[] = sprintf('<comment>Required File</comment>    %s', $definition->getFile() ? $definition->getFile() : '-');

        return $this->output(implode("\n", $description), $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerAlias(Alias $alias, array $options = array())
    {
        return $this->output(sprintf('This service is an alias for the service <info>%s</info>', (string) $alias), $options);
    }


    /**
     * @param ContainerBuilder $builder
     * @param array            $serviceIds
     * @param boolean          $showPrivate
     * @param boolean          $showTag
     *
     * @return array
     */
    private function describeServices(ContainerBuilder $builder, array $serviceIds, $showPrivate, $showTag)
    {
        // loop through to get space needed and filter private services
        $maxName = 4;
        $maxScope = 6;
        $maxTags = array();
        $serviceIds = $this->sortServiceIds($serviceIds);

        foreach ($serviceIds as $key => $serviceId) {
            $definition = $this->resolveServiceDefinition($builder, $serviceId);

            if ($definition instanceof Definition) {
                // filter out private services unless shown explicitly
                if (!$showPrivate && !$definition->isPublic()) {
                    unset($serviceIds[$key]);
                    continue;
                }

                if (strlen($definition->getScope()) > $maxScope) {
                    $maxScope = strlen($definition->getScope());
                }

                if (null !== $showTag) {
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

            if (strlen($serviceId) > $maxName) {
                $maxName = strlen($serviceId);
            }
        }

        $format  = '%-'.$maxName.'s ';
        $format .= implode('', array_map(function($length) { return '%-'.$length.'s '; }, $maxTags));
        $format .= '%-'.$maxScope.'s %s';

        $maxTagsCount = count($maxTags);

        $formatter = function ($format, $serviceId, $scope, $className, array $tagAttributes = array()) use ($format) {
            $arguments = array($serviceId);
            foreach ($tagAttributes as $tagAttribute) {
                $arguments[] = $tagAttribute;
            }
            $arguments[] = $scope;
            $arguments[] = $className;

            return vsprintf($format, $arguments);
        };

        $tags = array();
        foreach (array_keys($maxTags) as $tagName) {
            $tags[] = '<comment>'.$tagName.'</comment>';
        }

        $description = array($formatter(
            '%-'.($maxName + 19).'s '.implode('', array_map(function($length) {
                return '%-'.($length + 19).'s ';
            }, $maxTags)).'%-'.($maxScope + 19).'s %s',
            '<comment>Service Id</comment>',
            '<comment>Scope</comment>',
            '<comment>Class Name</comment>',
            $tags
        ));

        foreach ($serviceIds as $serviceId) {
            $definition = $this->resolveServiceDefinition($builder, $serviceId);
            if ($definition instanceof Definition) {
                if (null !== $showTag) {
                    foreach ($definition->getTag($showTag) as $key => $tag) {
                        $tagValues = array();
                        foreach (array_keys($maxTags) as $tagName) {
                            $tagValues[] = isset($tag[$tagName]) ? $tag[$tagName] : "";
                        }
                        if (0 === $key) {
                            $description[] = vsprintf($format, array_merge(array($serviceId), $tagValues, array($definition->getScope(), $definition->getClass())));
                        } else {
                            $description[] = vsprintf($format, array_merge(array('  "'), $tagValues, array('', '')));
                        }
                    }
                } else {
                    $description[] = sprintf($format, $serviceId, $definition->getScope(), $definition->getClass());
                }
            } elseif ($definition instanceof Alias) {
                $alias = $definition;
                $description[] = $formatter(
                    $format,
                    $serviceId,
                    'n/a',
                    sprintf('<comment>alias for</comment> <info>%s</info>', $alias),
                    $maxTagsCount ? array_fill(0, $maxTagsCount, "") : array()
                );
            } else {
                // we have no information (happens with "service_container")
                $service = $definition;
                $description[] = $formatter(
                    $format,
                    $serviceId,
                    '',
                    get_class($service),
                    $maxTagsCount ? array_fill(0, $maxTagsCount, "") : array()
                );
            }
        }

        return $description;
    }

    /**
     * @param array $array
     *
     * @return string
     */
    private function formatRouterConfig(array $array)
    {
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
     * @param string $description
     * @param array  $options
     *
     * @return string
     */
    private function output($description, array $options = array())
    {
        return isset($options['raw_text']) && $options['raw_text'] ? strip_tags($description) : $description;
    }
}
