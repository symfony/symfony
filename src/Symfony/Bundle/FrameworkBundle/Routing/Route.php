<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Routing;

use Symfony\Component\Routing\Route as BaseRoute;

/**
 * A framework-specific Route object to help with common route tasks.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class Route extends BaseRoute
{
    private $name;

    /**
     * Set the controller string on the route.
     *
     * @param string $controller
     *
     * @return $this
     */
    public function setController($controller)
    {
        $this->setDefault('_controller', $controller);

        return $this;
    }

    /**
     * Set the request format for this route.
     *
     * @param string $format
     *
     * @return $this
     */
    public function setRequestFormat($format)
    {
        $this->setDefault('_format', $format);

        return $this;
    }

    /**
     * Set the locale for this route.
     *
     * @param string $locale
     *
     * @return $this
     */
    public function setLocale($locale)
    {
        $this->setDefault('_locale', $locale);

        return $this;
    }

    /**
     * Set the name of this route - IF this route is added via a mechanism that
     * supports this, like RouteCollectionBuilder.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    /**
     * Generates a route name based on details of this route.
     *
     * @return string
     */
    public function generateRouteName()
    {
        $methods = implode('_', $this->getMethods()).'_';

        $routeName = $methods.$this->getPath();
        $routeName = str_replace(array('/', ':', '|', '-'), '_', $routeName);
        $routeName = preg_replace('/[^a-z0-9A-Z_.]+/', '', $routeName);

        // Collapse consecutive underscores down into a single underscore.
        $routeName = preg_replace('/_+/', '_', $routeName);

        return $routeName;
    }
}
