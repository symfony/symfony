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
use Symfony\Component\HttpKernel\Fragment\FragmentHandler;

/**
 * Provides integration with the HttpKernel component.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class HttpKernelRuntime
{
    private $handler;

    public function __construct(FragmentHandler $handler)
    {
        $this->handler = $handler;
    }

    /**
     * Renders a fragment.
     *
     * @param string|ControllerReference $uri A URI as a string or a ControllerReference instance
     *
     * @see FragmentHandler::render()
     */
    public function renderFragment($uri, array $options = []): string
    {
        $strategy = $options['strategy'] ?? 'inline';
        unset($options['strategy']);

        return $this->handler->render($uri, $strategy, $options);
    }

    /**
     * Renders a fragment.
     *
     * @param string|ControllerReference $uri A URI as a string or a ControllerReference instance
     *
     * @see FragmentHandler::render()
     */
    public function renderFragmentStrategy(string $strategy, $uri, array $options = []): string
    {
        return $this->handler->render($uri, $strategy, $options);
    }
}
