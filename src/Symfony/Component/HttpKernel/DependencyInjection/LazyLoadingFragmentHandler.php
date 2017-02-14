<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DependencyInjection;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Fragment\FragmentHandler;

/**
 * Lazily loads fragment renderers from the dependency injection container.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class LazyLoadingFragmentHandler extends FragmentHandler
{
    private $container;
    /**
     * @deprecated since version 3.3, to be removed in 4.0
     */
    private $rendererIds = array();
    private $initialized = array();

    /**
     * Constructor.
     *
     * @param ContainerInterface $container    A container
     * @param RequestStack       $requestStack The Request stack that controls the lifecycle of requests
     * @param bool               $debug        Whether the debug mode is enabled or not
     */
    public function __construct(ContainerInterface $container, RequestStack $requestStack, $debug = false)
    {
        $this->container = $container;

        parent::__construct($requestStack, array(), $debug);
    }

    /**
     * Adds a service as a fragment renderer.
     *
     * @param string $name     The service name
     * @param string $renderer The render service id
     *
     * @deprecated since version 3.3, to be removed in 4.0
     */
    public function addRendererService($name, $renderer)
    {
        @trigger_error(sprintf('The %s() method is deprecated since version 3.3 and will be removed in 4.0.', __METHOD__), E_USER_DEPRECATED);

        $this->rendererIds[$name] = $renderer;
    }

    /**
     * {@inheritdoc}
     */
    public function render($uri, $renderer = 'inline', array $options = array())
    {
        // BC 3.x, to be removed in 4.0
        if (isset($this->rendererIds[$renderer])) {
            $this->addRenderer($this->container->get($this->rendererIds[$renderer]));
            unset($this->rendererIds[$renderer]);

            return parent::render($uri, $renderer, $options);
        }

        if (!isset($this->initialized[$renderer]) && $this->container->has($renderer)) {
            $this->addRenderer($this->container->get($renderer));
            $this->initialized[$renderer] = true;
        }

        return parent::render($uri, $renderer, $options);
    }
}
