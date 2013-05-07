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
            case $object instanceof ContainerBuilder:
                return $this->describeContainerBuilder($object, $options);
            case $object instanceof Definition:
                return $this->describeContainerService($object, $options);
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
     * Describes a container.
     *
     * Common options are:
     * * services:   boolean (default true) adds services to description
     * * parameters: boolean (default true) adds parameters to description
     * * tags:       only describe tagged services, grouped by tag
     * * tag:        filters described services by given tag
     *
     * @param ContainerBuilder $builder
     * @param array            $options
     *
     * @return string|mixed
     */
    abstract protected function describeContainerBuilder(ContainerBuilder $builder, array $options = array());

    /**
     * Describes a service definition.
     *
     * @param Definition $definition
     * @param array      $options
     *
     * @return string|mixed
     */
    abstract protected function describeContainerService(Definition $definition, array $options = array());

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
}
