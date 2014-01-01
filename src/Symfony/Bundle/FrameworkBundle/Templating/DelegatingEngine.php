<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating;

use Symfony\Component\Templating\DelegatingEngine as BaseDelegatingEngine;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * DelegatingEngine selects an engine for a given template.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DelegatingEngine extends BaseDelegatingEngine implements EngineInterface
{
    protected $container;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container The DI container
     * @param array              $engineIds An array of engine Ids
     */
    public function __construct(ContainerInterface $container, array $engineIds)
    {
        $this->container = $container;
        $this->engines = $engineIds;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($name)
    {
        foreach ($this->engines as $i => $engine) {
            if (is_string($engine)) {
                $engine = $this->engines[$i] = $this->container->get($engine);
            }

            if ($engine->supports($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function getEngine($name)
    {
        foreach ($this->engines as $i => $engine) {
            if (is_string($engine)) {
                $engine = $this->engines[$i] = $this->container->get($engine);
            }

            if ($engine->supports($name)) {
                return $engine;
            }
        }

        throw new \RuntimeException(sprintf('No engine is able to work with the template "%s".', $name));
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
        return $this->getEngine($view)->renderResponse($view, $parameters, $response);
    }
}
