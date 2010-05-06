<?php

use Symfony\Components\DependencyInjection\Container;
use Symfony\Components\DependencyInjection\Reference;
use Symfony\Components\DependencyInjection\Parameter;

/**
 * Container
 *
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 */
class Container extends AbstractContainer
{
    protected $shared = array();

    /**
     * Returns service ids for a given annotation.
     *
     * @param string $name The annotation name
     *
     * @return array An array of annotations
     */
    public function findAnnotatedServiceIds($name)
    {
        static $annotations = array (
);

        return isset($annotations[$name]) ? $annotations[$name] : array();
    }
}
