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
        $serviceIds = isset($options['tag']) && $options['tag'] ? array_keys($builder->findTaggedServiceIds($options['tag'])) : $builder->getServiceIds();
        $showPrivate = isset($options['show_private']) && $options['show_private'];
        $output = array('definitions' => array(), 'aliases' => array(), 'services' => array());

        foreach ($serviceIds as $serviceId) {
            $service = $this->resolveServiceDefinition($builder, $serviceId);
            $childOptions = array('as_array' => true);

            if ($service instanceof Alias) {
                $output['aliases'][$serviceId] = $this->describeContainerAlias($service, $childOptions);
            } elseif ($service instanceof Definition) {
                if (($showPrivate || $service->isPublic())) {
                    $output['definitions'][$serviceId] = $this->describeContainerDefinition($service, $childOptions);
                }
            } else {
                $output['services'][$serviceId] = get_class($service);
            }
        }

        return $this->output($output, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerDefinition(Definition $definition, array $options = array())
    {
        $output = array(
            'class'     => (string) $definition->getClass(),
            'tags'      => array(),
            'scope'     => $definition->getScope(),
            'public'    => $definition->isPublic(),
            'synthetic' => $definition->isSynthetic(),
            'file'      => $definition->getFile(),
        );

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
        return $this->output(array(
            'service' => (string) $alias,
            'public'  => $alias->isPublic(),
        ), $options);
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
