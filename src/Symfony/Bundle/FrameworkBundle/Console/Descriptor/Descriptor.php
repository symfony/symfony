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

use Symfony\Component\Console\Descriptor\DescriptorInterface;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
abstract class Descriptor implements DescriptorInterface
{
    /**
     * {@inheritdoc}
     */
    public function describe($object, array $options = array())
    {
        switch (true) {
            case $object instanceof RouteCollection:
                return $this->describeRouteCollection($object, $options);
            case $object instanceof Route:
                return $this->describeRoute($object, $options);
            case $object instanceof ParameterBag:
                return $this->describeContainerParameters($object, $options);
            case $object instanceof ContainerBuilder:
                return isset($options['group_by']) && 'tags' === $options['group_by']
                    ? $this->describeContainerTags($object, $options)
                    : $this->describeContainerServices($object, $options);
            case $object instanceof Definition:
                return $this->describeContainerDefinition($object, $options);
            case $object instanceof Alias:
                return $this->describeContainerAlias($object, $options);
        }

        throw new \InvalidArgumentException(sprintf('Object of type "%s" is not describable.', get_class($object)));
    }

    /**
     * Describes an InputArgument instance.
     *
     * @param RouteCollection $routes
     * @param array           $options
     *
     * @return string|mixed
     */
    abstract protected function describeRouteCollection(RouteCollection $routes, array $options = array());

    /**
     * Describes an InputOption instance.
     *
     * @param Route $route
     * @param array $options
     *
     * @return string|mixed
     */
    abstract protected function describeRoute(Route $route, array $options = array());

    /**
     * Describes container parameters.
     *
     * @param ParameterBag $parameters
     * @param array        $options
     *
     * @return string|mixed
     */
    abstract protected function describeContainerParameters(ParameterBag $parameters, array $options = array());

    /**
     * Describes container tags.
     *
     * @param ContainerBuilder $builder
     * @param array            $options
     *
     * @return string|mixed
     */
    abstract protected function describeContainerTags(ContainerBuilder $builder, array $options = array());

    /**
     * Describes container services.
     *
     * Common options are:
     * * tag: filters described services by given tag
     *
     * @param ContainerBuilder $builder
     * @param array            $options
     *
     * @return string|mixed
     */
    abstract protected function describeContainerServices(ContainerBuilder $builder, array $options = array());

    /**
     * Describes a service definition.
     *
     * @param Definition $definition
     * @param array      $options
     *
     * @return string|mixed
     */
    abstract protected function describeContainerDefinition(Definition $definition, array $options = array());

    /**
     * Describes a service alias.
     *
     * @param Alias $alias
     * @param array $options
     *
     * @return string|mixed
     */
    abstract protected function describeContainerAlias(Alias $alias, array $options = array());

    /**
     * Formats a value as string.
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function formatValue($value)
    {
        if (is_object($value)) {
            return sprintf('object(%s)', get_class($value));
        }

        if (is_string($value)) {
            return $value;
        }

        return preg_replace("/\n\s*/s", '', var_export($value, true));
    }

    /**
     * Formats a parameter.
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function formatParameter($value)
    {
        if (is_bool($value) || is_array($value) || (null === $value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    /**
     * @param ContainerBuilder $builder
     * @param string           $serviceId
     *
     * @return mixed
     */
    protected function resolveServiceDefinition(ContainerBuilder $builder, $serviceId)
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
     * @param ContainerBuilder $builder
     * @param boolean          $showPrivate
     *
     * @return array
     */
    protected function findDefinitionsByTag(ContainerBuilder $builder, $showPrivate)
    {
        $definitions = array();
        $tags = $builder->findTags();
        asort($tags);

        foreach ($tags as $tag) {
            foreach ($builder->findTaggedServiceIds($tag) as $serviceId => $attributes) {
                $definition = $this->resolveServiceDefinition($builder, $serviceId);

                if (!$definition instanceof Definition || !$showPrivate && !$definition->isPublic()) {
                    continue;
                }

                if (!isset($definitions[$tag])) {
                    $definitions[$tag] = array();
                }

                $definitions[$tag][$serviceId] = $definition;
            }
        }

        return $definitions;
    }
}
