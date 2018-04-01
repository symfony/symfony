<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Twig\Extension;

use Symphony\Component\HttpKernel\Controller\ControllerReference;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Provides integration with the HttpKernel component.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class HttpKernelExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return array(
            new TwigFunction('render', array(HttpKernelRuntime::class, 'renderFragment'), array('is_safe' => array('html'))),
            new TwigFunction('render_*', array(HttpKernelRuntime::class, 'renderFragmentStrategy'), array('is_safe' => array('html'))),
            new TwigFunction('controller', static::class.'::controller'),
        );
    }

    public static function controller($controller, $attributes = array(), $query = array())
    {
        return new ControllerReference($controller, $attributes, $query);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'http_kernel';
    }
}
