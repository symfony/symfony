<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\HttpKernel\Controller\ControllerReference;

/**
 * Provides integration with the HttpKernel component.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HttpKernelExtension extends \Twig_Extension
{
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('render', array(HttpKernelRuntime::class, 'renderFragment'), array('is_safe' => array('html'))),
            new \Twig_SimpleFunction('render_*', array(HttpKernelRuntime::class, 'renderFragmentStrategy'), array('is_safe' => array('html'))),
            new \Twig_SimpleFunction('controller', static::class.'::controller'),
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
