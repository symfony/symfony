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

use Symfony\Bundle\FrameworkBundle\Exception\LogicException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Templating\StreamingEngineInterface;

/**
 * A collection of utility functions for rendering views.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexander M. Turek <me@derrabus.de>
 */
trait RenderHelperTrait
{
    /**
     * @var \Twig_Environment
     */
    protected $twig;

    /**
     * @var EngineInterface|StreamingEngineInterface
     */
    protected $templating;

    protected function ensureRenderer()
    {
        if ($this->twig !== null || $this->templating !== null) {
            return;
        }

        if (!isset($this->container)) {
            throw new LogicException('Unable to load a renderer. Please either set the $twig or $templating property or make'.__CLASS__.' container-aware.');
        }

        if ($this->container->has('templating')) {
            $this->templating = $this->container->get('templating');

            return;
        }

        if ($this->container->has('twig')) {
            $this->twig = $this->container->get('twig');

            return;
        }

        throw new LogicException('You can not use '.__TRAIT__.' if the Templating Component or the Twig Bundle are not available.');
    }

    /**
     * Returns a rendered view.
     *
     * @param string $view       The view name
     * @param array  $parameters An array of parameters to pass to the view
     *
     * @return string The rendered view
     */
    protected function renderView($view, array $parameters = array())
    {
        $this->ensureRenderer();

        if ($this->templating !== null) {
            return $this->templating->render($view, $parameters);
        }

        return $this->twig->render($view, $parameters);
    }

    /**
     * Renders a view.
     *
     * @param string   $view       The view name
     * @param array    $parameters An array of parameters to pass to the view
     * @param Response $response   A response instance
     *
     * @return Response A Response instance
     */
    protected function render($view, array $parameters = array(), Response $response = null)
    {
        $this->ensureRenderer();

        if ($this->templating !== null) {
            return $this->templating->renderResponse($view, $parameters, $response);
        }

        if (null === $response) {
            $response = new Response();
        }

        $response->setContent($this->twig->render($view, $parameters));

        return $response;
    }

    /**
     * Streams a view.
     *
     * @param string           $view       The view name
     * @param array            $parameters An array of parameters to pass to the view
     * @param StreamedResponse $response   A response instance
     *
     * @return StreamedResponse A StreamedResponse instance
     */
    protected function stream($view, array $parameters = array(), StreamedResponse $response = null)
    {
        $this->ensureRenderer();

        if ($this->templating !== null) {
            $callback = function () use ($view, $parameters) {
                $this->templating->stream($view, $parameters);
            };
        } else {
            $callback = function () use ($view, $parameters) {
                $this->twig->display($view, $parameters);
            };
        }

        if (null === $response) {
            return new StreamedResponse($callback);
        }

        $response->setCallback($callback);

        return $response;
    }
}
