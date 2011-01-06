<?php

namespace Symfony\Bundle\FrameworkBundle\Templating;

use Symfony\Component\Templating\Engine as BaseEngine;
use Symfony\Component\Templating\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * This engine knows how to render Symfony templates.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Engine extends BaseEngine
{
    protected $container;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container The DI container
     * @param LoaderInterface    $loader    A loader instance
     * @param array              $renderers All templating renderers
     */
    public function __construct(ContainerInterface $container, LoaderInterface $loader)
    {
        $this->container = $container;

        parent::__construct($loader);
    }

    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Renders a view and returns a Response.
     *
     * @param string   $view       The view name
     * @param array    $parameters An array of parameters to pass to the view
     * @param Response $response   A Response instance
     *
     * @return Response A Response instance
     */
    public function renderResponse($view, array $parameters = array(), Response $response = null)
    {
        if (null === $response) {
            $response = $this->container->get('response');
        }

        $response->setContent($this->render($view, $parameters));

        return $response;
    }

    public function has($name)
    {
        return isset($this->helpers[$name]);
    }

    /**
     * @throws \InvalidArgumentException When the helper is not defined
     */
    public function get($name)
    {
        if (!isset($this->helpers[$name])) {
            throw new \InvalidArgumentException(sprintf('The helper "%s" is not defined.', $name));
        }

        if (is_string($this->helpers[$name])) {
            $this->helpers[$name] = $this->container->get($this->helpers[$name]);
            $this->helpers[$name]->setCharset($this->charset);
        }

        return $this->helpers[$name];
    }

    public function setHelpers(array $helpers)
    {
        $this->helpers = $helpers;
    }

    /**
     * {@inheritdoc}
     */
    public function splitTemplateName($name, array $defaults = array())
    {
        return $this->container->get('templating.name_converter')->fromShortNotation($name, $defaults);
    }
}
