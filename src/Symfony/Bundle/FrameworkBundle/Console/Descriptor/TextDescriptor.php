<?php

namespace Symfony\Bundle\FrameworkBundle\Console\Descriptor;

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
        $outputs[] = sprintf($formatHeader, '<comment>Name</comment>', '<comment>Method</comment>',  '<comment>Scheme</comment>', '<comment>Host</comment>', '<comment>Path</comment>');

        foreach ($routes->all() as $name => $route) {
            $method = $route->getMethods() ? implode('|', $route->getMethods()) : 'ANY';
            $scheme = $route->getSchemes() ? implode('|', $route->getSchemes()) : 'ANY';
            $host = '' !== $route->getHost() ? $route->getHost() : 'ANY';
            // fixme: this line was originally written as raw
            $outputs[] = sprintf($format, $name, $method, $scheme, $host, $route->getPath());
        }

        return implode("\n", $outputs);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeRoute(Route $route, array $options = array())
    {
        $requirements = $route->getRequirements();
        unset($requirements['_scheme'], $requirements['_method']);

        // fixme: values were originally written as raw
        $outputs = array(
            '<comment>Path</comment>         '.$route->getPath(),
            '<comment>Host</comment>         '.('' !== $route->getHost() ? $route->getHost() : 'ANY'),
            '<comment>Scheme</comment>       '.($route->getSchemes() ? implode('|', $route->getSchemes()) : 'ANY'),
            '<comment>Method</comment>       '.($route->getMethods() ? implode('|', $route->getMethods()) : 'ANY'),
            '<comment>Class</comment>        '.get_class($route),
            '<comment>Defaults</comment>     '.$this->formatConfigs($route->getDefaults()),
            '<comment>Requirements</comment> '.$this->formatConfigs($requirements) ?: 'NO CUSTOM',
            '<comment>Options</comment>      '.$this->formatConfigs($route->getOptions()),
            '<comment>Path-Regex</comment>   '.$route->compile()->getRegex(),
        );

        if (isset($options['name'])) {
            array_unshift($outputs, '<comment>Name</comment>         '.$options['name']);
        }

        if (null !== $route->compile()->getHostRegex()) {
            $outputs[] = '<comment>Host-Regex</comment>   '.$route->compile()->getHostRegex();
        }

        return implode("\n", $outputs);
    }

    private function formatConfigs(array $array)
    {
        $string = '';
        ksort($array);
        foreach ($array as $name => $value) {
            $string .= ($string ? "\n".str_repeat(' ', 13) : '').$name.': '.$this->formatValue($value);
        }

        return $string;
    }
}
