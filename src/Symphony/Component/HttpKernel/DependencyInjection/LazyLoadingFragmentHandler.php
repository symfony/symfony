<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\DependencyInjection;

use Psr\Container\ContainerInterface;
use Symphony\Component\HttpFoundation\RequestStack;
use Symphony\Component\HttpKernel\Fragment\FragmentHandler;

/**
 * Lazily loads fragment renderers from the dependency injection container.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class LazyLoadingFragmentHandler extends FragmentHandler
{
    private $container;
    private $initialized = array();

    public function __construct(ContainerInterface $container, RequestStack $requestStack, bool $debug = false)
    {
        $this->container = $container;

        parent::__construct($requestStack, array(), $debug);
    }

    /**
     * {@inheritdoc}
     */
    public function render($uri, $renderer = 'inline', array $options = array())
    {
        if (!isset($this->initialized[$renderer]) && $this->container->has($renderer)) {
            $this->addRenderer($this->container->get($renderer));
            $this->initialized[$renderer] = true;
        }

        return parent::render($uri, $renderer, $options);
    }
}
