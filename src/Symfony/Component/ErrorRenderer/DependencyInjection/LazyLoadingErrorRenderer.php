<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorRenderer\DependencyInjection;

use Psr\Container\ContainerInterface;
use Symfony\Component\ErrorRenderer\ErrorRenderer;

/**
 * Lazily loads error renderers from the dependency injection container.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class LazyLoadingErrorRenderer extends ErrorRenderer
{
    private $container;
    private $initialized = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function render($exception, string $format = 'html'): string
    {
        if (!isset($this->initialized[$format]) && $this->container->has($format)) {
            $this->addRenderer($this->container->get($format), $format);
            $this->initialized[$format] = true;
        }

        return parent::render($exception, $format);
    }
}
