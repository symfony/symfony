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
    protected function describeContainerBuilder(ContainerBuilder $builder, array $options = array())
    {
        // TODO: Implement describeContainerBuilder() method.
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerService(Definition $definition, array $options = array())
    {
        $output = '- Class: `'.$definition->getClass().'`'
            ."\n".'- Scope: `'.$definition->getScope().'`'
            ."\n".'- Public: '.($definition->isPublic() ? 'yes' : 'no')
            ."\n".'- Synthetic: '.($definition->isSynthetic() ? 'yes' : 'no');

        if ($definition->getFile()) {
            $output .= "\n".'- File: `'.$definition->getFile().'`';
        }

        foreach ($definition->getTags() as $tagName => $tagData) {
            foreach ($tagData as $parameters) {
                $output .= "\n".'- Tag: `'.$tagName.'`';
                foreach ($parameters as $name => $value) {
                    $output .= "\n".'    - '.ucfirst($name).': '.$value;
                }
            }
        }

        return isset($options['id']) ? sprintf("**%s:**\n%s", $options['id'], $output) : $output;
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerAlias(Alias $alias, array $options = array())
    {
        $output = '- Service: `'.$alias.'`'
            ."\n".'- Public: '.($alias->isPublic() ? 'yes' : 'no');

        return isset($options['id']) ? sprintf("**%s:**\n%s", $options['id'], $output) : $output;
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
