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
     * @param ContainerInterface $container A ContainerInterface instance
     * @param LoaderInterface    $loader    A loader instance
     */
    public function __construct(ContainerInterface $container, LoaderInterface $loader)
    {
        $this->container = $container;

        parent::__construct($loader);

        foreach ($this->container->findTaggedServiceIds('templating.renderer') as $id => $attributes) {
            if (isset($attributes[0]['alias'])) {
                $this->renderers[$attributes[0]['alias']] = $this->container->get($id);
                $this->renderers[$attributes[0]['alias']]->setEngine($this);
            }
        }

        $this->helpers = array();
        foreach ($this->container->findTaggedServiceIds('templating.helper') as $id => $attributes) {
            if (isset($attributes[0]['alias'])) {
                $this->helpers[$attributes[0]['alias']] = $id;
            }
        }
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

    // parses template names following the following pattern:
    // bundle:section:template(.format).renderer
    public function splitTemplateName($name, array $defaults = array())
    {
        $parts = explode(':', $name);
        if (3 !== count($parts)) {
            throw new \InvalidArgumentException(sprintf('Template name "%s" is not valid.', $name));
        }

        $options = array_replace(
            array(
                'format' => '',
            ),
            $defaults,
            array(
                'bundle'     => str_replace('\\', '/', $parts[0]),
                'controller' => $parts[1],
            )
        );

        $elements = explode('.', $parts[2]);
        if (3 === count($elements)) {
            $parts[2] = $elements[0];
            if ('html' !== $elements[1]) {
                $options['format'] = '.'.$elements[1];
            }
            $options['renderer'] = $elements[2];
        } elseif (2 === count($elements)) {
            $parts[2] = $elements[0];
            $options['renderer'] = $elements[1];
            $format = $this->container->get('request')->getRequestFormat();
            if (null !== $format && 'html' !== $format) {
                $options['format'] = '.'.$format;
            }
        } else {
            throw new \InvalidArgumentException(sprintf('Template name "%s" is not valid.', $name));
        }

        return array($parts[2], $options);
    }
}
