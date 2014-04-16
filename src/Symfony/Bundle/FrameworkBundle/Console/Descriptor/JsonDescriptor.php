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

if (!defined('JSON_PRETTY_PRINT')) {
    define('JSON_PRETTY_PRINT', 128);
}

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
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
            $data[$name] = $this->getRouteData($route);
        }

        $this->writeData($data, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeRoute(Route $route, array $options = array())
    {
        $this->writeData($this->getRouteData($route), $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerParameters(ParameterBag $parameters, array $options = array())
    {
        $this->writeData($this->sortParameters($parameters), $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerTags(ContainerBuilder $builder, array $options = array())
    {
        $showPrivate = isset($options['show_private']) && $options['show_private'];
        $data = array();

        foreach ($this->findDefinitionsByTag($builder, $showPrivate) as $tag => $definitions) {
            $data[$tag] = array();
            foreach ($definitions as $definition) {
                $data[$tag][] = $this->getContainerDefinitionData($definition, true);
            }
        }

        $this->writeData($data, $options);
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
            $this->writeData($this->getContainerAliasData($service), $options);
        } elseif ($service instanceof Definition) {
            $this->writeData($this->getContainerDefinitionData($service), $options);
        } else {
            $this->writeData(get_class($service), $options);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerServices(ContainerBuilder $builder, array $options = array())
    {
        $serviceIds = isset($options['tag']) && $options['tag'] ? array_keys($builder->findTaggedServiceIds($options['tag'])) : $builder->getServiceIds();
        $showPrivate = isset($options['show_private']) && $options['show_private'];
        $data = array('definitions' => array(), 'aliases' => array(), 'services' => array());

        foreach ($this->sortServiceIds($serviceIds) as $serviceId) {
            $service = $this->resolveServiceDefinition($builder, $serviceId);

            if ($service instanceof Alias) {
                $data['aliases'][$serviceId] = $this->getContainerAliasData($service);
            } elseif ($service instanceof Definition) {
                if (($showPrivate || $service->isPublic())) {
                    $data['definitions'][$serviceId] = $this->getContainerDefinitionData($service);
                }
            } else {
                $data['services'][$serviceId] = get_class($service);
            }
        }

        $this->writeData($data, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerDefinition(Definition $definition, array $options = array())
    {
        $this->writeData($this->getContainerDefinitionData($definition, isset($options['omit_tags']) && $options['omit_tags']), $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeContainerAlias(Alias $alias, array $options = array())
    {
        $this->writeData($this->getContainerAliasData($alias), $options);
    }

    /**
     * Writes data as json.
     *
     * @param array $data
     * @param array $options
     *
     * @return array|string
     */
    private function writeData(array $data, array $options)
    {
        $this->write(json_encode($data, (isset($options['json_encoding']) ? $options['json_encoding'] : 0) | JSON_PRETTY_PRINT)."\n");
    }

    /**
     * @param Route $route
     *
     * @return array
     */
    protected function getRouteData(Route $route)
    {
        $requirements = $route->getRequirements();
        unset($requirements['_scheme'], $requirements['_method']);

        return array(
            'path'         => $route->getPath(),
            'host'         => '' !== $route->getHost() ? $route->getHost() : 'ANY',
            'scheme'       => $route->getSchemes() ? implode('|', $route->getSchemes()) : 'ANY',
            'method'       => $route->getMethods() ? implode('|', $route->getMethods()) : 'ANY',
            'class'        => get_class($route),
            'defaults'     => $route->getDefaults(),
            'requirements' => $requirements ?: 'NO CUSTOM',
            'options'      => $route->getOptions(),
            'pathRegex'    => $route->compile()->getRegex(),
        );
    }

    /**
     * @param Definition $definition
     * @param bool       $omitTags
     *
     * @return array
     */
    private function getContainerDefinitionData(Definition $definition, $omitTags = false)
    {
        $data = array(
            'class'     => (string) $definition->getClass(),
            'scope'     => $definition->getScope(),
            'public'    => $definition->isPublic(),
            'synthetic' => $definition->isSynthetic(),
            'file'      => $definition->getFile(),
        );

        if (!$omitTags) {
            $data['tags'] = array();
            if (count($definition->getTags())) {
                foreach ($definition->getTags() as $tagName => $tagData) {
                    foreach ($tagData as $parameters) {
                        $data['tags'][] = array('name' => $tagName, 'parameters' => $parameters);
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @param Alias $alias
     *
     * @return array
     */
    private function getContainerAliasData(Alias $alias)
    {
        return array(
            'service' => (string) $alias,
            'public'  => $alias->isPublic(),
        );
    }
}
