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
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Provides integration with the HttpKernel component.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final since Symfony 4.4
 */
class HttpKernelExtension extends AbstractExtension
{
    /**
     * @return TwigFunction[]
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('render', [HttpKernelRuntime::class, 'renderFragment'], ['is_safe' => ['html']]),
            new TwigFunction('render_*', [HttpKernelRuntime::class, 'renderFragmentStrategy'], ['is_safe' => ['html']]),
            new TwigFunction('controller', static::class.'::controller'),
        ];
    }

    public static function controller($controller, $attributes = [], $query = [])
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
