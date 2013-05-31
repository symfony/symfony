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
class JsonDescriptor extends Descriptor
{
    /**
     * {@inheritdoc}
     */
    protected function describeRouteCollection(RouteCollection $routes, array $options = array())
    {
        $data = array();
        foreach ($routes->all() as $name => $route) {
            $data[$name] = $this->describeRoute($route, array('as_array' => true));
        }

        return $this->output($data, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeRoute(Route $route, array $options = array())
    {
        $requirements = $route->getRequirements();
        unset($requirements['_scheme'], $requirements['_method']);

        return $this->output(array(
            'path'         => $route->getPath(),
            'host'         => '' !== $route->getHost() ? $route->getHost() : 'ANY',
            'scheme'       => $route->getSchemes() ? implode('|', $route->getSchemes()) : 'ANY',
            'method'       => $route->getMethods() ? implode('|', $route->getMethods()) : 'ANY',
            'class'        => get_class($route),
            'defaults'     => $route->getDefaults(),
            'requirements' => $requirements ?: 'NO CUSTOM',
            'options'      => $route->getOptions(),
            'pathRegex'    => $route->compile()->getRegex(),
        ), $options);
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
        $output = isset($options['id']) ? array('id' => $options['id']) : array();
        $output['class'] = (string) $definition->getClass();
        $output['tags'] = array();
        $output['scope'] = $definition->getScope();
        $output['public'] = $definition->isPublic();
        $output['synthetic'] = $definition->isSynthetic();
        $output['file'] = $definition->getFile();

        if (count($definition->getTags())) {
            foreach ($definition->getTags() as $tagName => $tagData) {
                foreach ($tagData as $parameters) {
                    $output['tags'][] = array('name' => $tagName, 'parameters' => $parameters);
                }
            }
        }

        return $this->output($output, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerAlias(Alias $alias, array $options = array())
    {
        $output = isset($options['id']) ? array('id' => $options['id']) : array();
        $output['service'] = (string) $alias;
        $output['public'] = $alias->isPublic();

        return $this->output($output, $options);
    }

    /**
     * Outputs data as array or string according to options.
     *
     * @param array $data
     * @param array $options
     *
     * @return array|string
     */
    private function output(array $data, array $options)
    {
        if (isset($options['as_array']) && $options['as_array']) {
            return $data;
        }

        return json_encode($data, isset($options['json_encoding']) ? $options['json_encoding'] : 0);
    }
}
