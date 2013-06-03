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
class MarkdownDescriptor extends Descriptor
{
    /**
     * {@inheritdoc}
     */
    protected function describeRouteCollection(RouteCollection $routes, array $options = array())
    {
        $outputs = array();
        foreach ($routes->all() as $name => $route) {
            $outputs[] = $this->describeRoute($route, array('name' => $name));
        }

        return implode("\n\n", $outputs);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeRoute(Route $route, array $options = array())
    {
        $requirements = $route->getRequirements();
        unset($requirements['_scheme'], $requirements['_method']);

        $output = '- Path: '.$route->getPath()
            ."\n".'- Host: '.('' !== $route->getHost() ? $route->getHost() : 'ANY')
            ."\n".'- Scheme: '.($route->getSchemes() ? implode('|', $route->getSchemes()) : 'ANY')
            ."\n".'- Method: '.($route->getMethods() ? implode('|', $route->getMethods()) : 'ANY')
            ."\n".'- Class: '.get_class($route)
            ."\n".'- Defaults: '.$this->formatRouterConfig($route->getDefaults())
            ."\n".'- Requirements: '.$this->formatRouterConfig($requirements) ?: 'NONE'
            ."\n".'- Options: '.$this->formatRouterConfig($route->getOptions())
            ."\n".'- Path-Regex: '.$route->compile()->getRegex();

        return isset($options['name'])
            ? $options['name']."\n".str_repeat('-', strlen($options['name']))."\n".$output
            : $output;
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerParameters(ParameterBag $parameters, array $options = array())
    {
        $output = "Container parameters\n====================\n";
        foreach ($parameters->all() as $key => $value) {
            $output .= sprintf("\n- `%s`: `%s`", $key, $this->formatParameter($value));
        }

        return $output;
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerTags(ContainerBuilder $builder, array $options = array())
    {
        $showPrivate = isset($options['show_private']) && $options['show_private'];
        $output = "Container tags\n==============";

        foreach ($this->findDefinitionsByTag($builder, $showPrivate) as $tag => $definitions) {
            $output .= "\n\n".$tag."\n".str_repeat('-', strlen($tag));
            foreach ($definitions as $serviceId => $definition) {
                $output .= "\n\n";
                $output .= $this->describeContainerDefinition($definition, array('omit_tags' => true, 'id' => $serviceId));
            }
        }

        return $output;
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerServices(ContainerBuilder $builder, array $options = array())
    {
        $showPrivate = isset($options['show_private']) && $options['show_private'];

        $title = $showPrivate ? 'Public and private services' : 'Public services';
        if (isset($options['tag'])) {
            $title .= ' with tag `'.$options['tag'].'`';
        }
        $title .= "\n".str_repeat('=', strlen($title));

        $serviceIds = isset($options['tag']) && $options['tag'] ? array_keys($builder->findTaggedServiceIds($options['tag'])) : $builder->getServiceIds();
        $showPrivate = isset($options['show_private']) && $options['show_private'];
        $output = array('definitions' => array(), 'aliases' => array(), 'services' => array());

        foreach ($serviceIds as $serviceId) {
            $service = $this->resolveServiceDefinition($builder, $serviceId);
            $childOptions = array('id' => $serviceId);

            if ($service instanceof Alias) {
                $output['aliases'][] = $this->describeContainerAlias($service, $childOptions);
            } elseif ($service instanceof Definition) {
                if (($showPrivate || $service->isPublic())) {
                    $output['definitions'][] = $this->describeContainerDefinition($service, $childOptions);
                }
            } else {
                $output['services'][] = sprintf('- `%s`: `%s`', $serviceId, get_class($service));
            }
        }

        $format = function ($items, $title) {
            return empty($items) ? '' : "\n\n".$title."\n".str_repeat('-', strlen($title))."\n\n".implode("\n\n", $items);
        };

        return $title
            .$format($output['definitions'], 'Definitions')
            .$format($output['aliases'], 'Aliases')
            .$format($output['services'], 'Services');
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerDefinition(Definition $definition, array $options = array())
    {
        $output = '- Class: `'.$definition->getClass().'`'
            ."\n".'- Scope: `'.$definition->getScope().'`'
            ."\n".'- Public: '.($definition->isPublic() ? 'yes' : 'no')
            ."\n".'- Synthetic: '.($definition->isSynthetic() ? 'yes' : 'no');

        if ($definition->getFile()) {
            $output .= "\n".'- File: `'.$definition->getFile().'`';
        }

        if (!(isset($options['omit_tags']) && $options['omit_tags'])) {
            foreach ($definition->getTags() as $tagName => $tagData) {
                foreach ($tagData as $parameters) {
                    $output .= "\n".'- Tag: `'.$tagName.'`';
                    foreach ($parameters as $name => $value) {
                        $output .= "\n".'    - '.ucfirst($name).': '.$value;
                    }
                }
            }
        }

        return isset($options['id']) ? sprintf("**`%s`:**\n%s", $options['id'], $output) : $output;
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerAlias(Alias $alias, array $options = array())
    {
        $output = '- Service: `'.$alias.'`'
            ."\n".'- Public: '.($alias->isPublic() ? 'yes' : 'no');

        return isset($options['id']) ? sprintf("**`%s`:**\n%s", $options['id'], $output) : $output;
    }

    private function formatRouterConfig(array $array)
    {
        if (!count($array)) {
            return 'NONE';
        }

        $string = '';
        ksort($array);
        foreach ($array as $name => $value) {
            $string .= "\n".'    - `'.$name.'`: '.$this->formatValue($value);
        }

        return $string;
    }
}
