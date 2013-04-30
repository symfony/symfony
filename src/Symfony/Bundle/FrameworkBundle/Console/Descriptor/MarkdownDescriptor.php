<?php

namespace Symfony\Bundle\FrameworkBundle\Console\Descriptor;

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
            ."\n".'- Defaults: '.$this->formatConfigs($route->getDefaults())
            ."\n".'- Requirements: '.$this->formatConfigs($requirements) ?: 'NO CUSTOM'
            ."\n".'- Options: '.$this->formatConfigs($route->getOptions())
            ."\n".'- Path-Regex: '.$route->compile()->getRegex();

        return isset($options['name'])
            ? $options['name']."\n".str_repeat('-', strlen($options['name']))."\n".$output
            : $output;
    }

    private function formatConfigs(array $array)
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
