<?php

namespace Symfony\Bundle\FrameworkBundle\Console\Descriptor;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
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

        foreach ($routes->all() as $name => $route) {
            $method = $route->getMethods() ? implode('|', $route->getMethods()) : 'ANY';
            $scheme = $route->getSchemes() ? implode('|', $route->getSchemes()) : 'ANY';
            $host = '' !== $route->getHost() ? $route->getHost() : 'ANY';
            $maxName = max($maxName, strlen($name));
            $maxMethod = max($maxMethod, strlen($method));
            $maxScheme = max($maxScheme, strlen($scheme));
            $maxHost = max($maxHost, strlen($host));
        }

        $format  = '%-'.$maxName.'s %-'.$maxMethod.'s %-'.$maxScheme.'s %-'.$maxHost.'s %s';
        $formatHeader  = '%-'.($maxName + 19).'s %-'.($maxMethod + 19).'s %-'.($maxScheme + 19).'s %-'.($maxHost + 19).'s %s';
        $description = array(sprintf($formatHeader, '<comment>Name</comment>', '<comment>Method</comment>',  '<comment>Scheme</comment>', '<comment>Host</comment>', '<comment>Path</comment>'));

        foreach ($routes->all() as $name => $route) {
            $method = $route->getMethods() ? implode('|', $route->getMethods()) : 'ANY';
            $scheme = $route->getSchemes() ? implode('|', $route->getSchemes()) : 'ANY';
            $host = '' !== $route->getHost() ? $route->getHost() : 'ANY';
            // fixme: this line was originally written as raw
            $description[] = sprintf($format, $name, $method, $scheme, $host, $route->getPath());
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
        }

        if (null !== $route->compile()->getHostRegex()) {
            $description[] = '<comment>Host-Regex</comment>   '.$route->compile()->getHostRegex();
        }

        return $this->output(implode("\n", $description), $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerBuilder(ContainerBuilder $builder, array $options = array())
    {
        if (isset($options['type']) && 'tags' === $options['type']) {
            return $this->output(implode("\n", $this->getContainerBuilderTagsDescription($builder)), $options);
        }

        if (isset($options['type']) && 'parameters' === $options['type']) {
            return $this->output(implode("\n", $this->getContainerBuilderParametersDescription($builder)), $options);
        }

        $showPrivate = isset($options['show_private']) && $options['show_private'];
        $showTagAttributes = isset($options['show_tag_attributes']) && $options['show_tag_attributes'];

        if ($showPrivate) {
            $label = '<comment>Public</comment> and <comment>private</comment> services';
        } else {
            $label = '<comment>Public</comment> services';
        }

        if ($showTagAttributes) {
            $label .= ' with tag <info>'.$options['tag_attributes'].'</info>';
        }

        $serviceIds = isset($options['tag']) && $options['tag'] ? $builder->findTaggedServiceIds($options['tag']) : $builder->getServiceIds();
        $description = $this->getContainerBuilderServicesDescription($builder, $serviceIds, $showPrivate, $showTagAttributes);

        return $this->output($label."\n".implode("\n", $description), $options);
    }

    /**
     * @param ContainerBuilder $builder
     * @param array            $serviceIds
     * @param boolean          $showPrivate
     * @param boolean          $showTagAttributes
     *
     * @return array
     */
    public function getContainerBuilderServicesDescription(ContainerBuilder $builder, array $serviceIds, $showPrivate, $showTagAttributes)
    {
        // loop through to get space needed and filter private services
        $maxName = 4;
        $maxScope = 6;
        $maxTags = array();

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

                if (null !== $showTagAttributes) {
                    $tags = $definition->getTag($showTagAttributes);
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
        $format .= implode("", array_map(function($length) { return "%-{$length}s "; }, $maxTags));
        $format .= '%-'.$maxScope.'s %s';

        $formatter = function ($serviceId, $scope, $className, array $tagAttributes = array()) use ($format) {
            $arguments = array($serviceId);
            foreach ($tagAttributes as $tagAttribute) {
                $arguments[] = $tagAttribute;
            }
            $arguments[] = $scope;
            $arguments[] = $className;

            return vsprintf($format, $arguments);
        };

        $tags = array();
        foreach ($maxTags as $tagName => $length) {
            $tags[] = '<comment>'.$tagName.'</comment>';
        }

        $description = $formatter('%-'.($maxName + 19).'s '.implode('', array_map(function($length) { return '%-'.($length + 19).'s '; }, $maxTags)).'%-'.($maxScope + 19).'s %s', '<comment>Service Id</comment>', '<comment>Scope</comment>', '<comment>Class Name</comment>', $tags);

        foreach ($serviceIds as $serviceId) {
            $definition = $this->resolveServiceDefinition($builder, $serviceId);
            if ($definition instanceof Definition) {
                if (null !== $showTagAttributes) {
                    foreach ($definition->getTag($showTagAttributes) as $key => $tag) {
                        $tagValues = array();
                        foreach (array_keys($maxTags) as $tagName) {
                            $tagValues[] = isset($tag[$tagName]) ? $tag[$tagName] : "";
                        }
                        if (0 === $key) {
                            $description[] = vsprintf($format, $serviceId, $definition->getScope(), $definition->getClass(), $tagValues);
                        } else {
                            $description[] = vsprintf($format, '  "', '', '', $tagValues);
                        }
                    }
                } else {
                    $description[] = vsprintf($format, $serviceId, $definition->getScope(), $definition->getClass());
                }
            } elseif ($definition instanceof Alias) {
                $alias = $definition;
                $description[] = $formatter($format, $serviceId, 'n/a', sprintf('<comment>alias for</comment> <info>%s</info>', (string) $alias), count($maxTags) ? array_fill(0, count($maxTags), "") : array());
            } else {
                // we have no information (happens with "service_container")
                $service = $definition;
                $description[] = $formatter($format, $serviceId, '', get_class($service), count($maxTags) ? array_fill(0, count($maxTags), "") : array());
            }
        }

        return $description;
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerService(Definition $definition, array $options = array())
    {
        $description = array(
            sprintf('<comment>Service Id</comment>       %s', isset($options['id']) ? $options['id'] : '-'),
            sprintf('<comment>Class</comment>            %s', $definition->getClass() ?: "-"),
        );

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
     * @param ContainerBuilder $builder
     * @param string           $serviceId
     *
     * @return mixed
     */
    private function resolveServiceDefinition(ContainerBuilder $builder, $serviceId)
    {
        if ($builder->hasDefinition($serviceId)) {
            return $builder->getDefinition($serviceId);
        }

        // Some service IDs don't have a Definition, they're simply an Alias
        if ($builder->hasAlias($serviceId)) {
            return $builder->getAlias($serviceId);
        }

        // the service has been injected in some special way, just return the service
        return $builder->get($serviceId);
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
