<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\RenderingStrategy\RenderingStrategyInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Interface to be implemented by Http content renderers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface HttpContentRendererInterface
{
    /**
     * Adds a rendering strategy.
     *
     * @param RenderingStrategyInterface $strategy A RenderingStrategyInterface instance
     */
    public function addStrategy(RenderingStrategyInterface $strategy);

    /**
     * Renders a URI and returns the Response content.
     *
     * When the Response is a StreamedResponse, the content is streamed immediately
     * instead of being returned.
     *
     * Available options:
     *
     *  * ignore_errors: true to return an empty string in case of an error
     *
     * @param string|ControllerReference $uri      A URI as a string or a ControllerReference instance
     * @param string                     $strategy The strategy to use for the rendering
     * @param array                      $options  An array of options
     *
     * @return string|null The Response content or null when the Response is streamed
     *
     * @throws \InvalidArgumentException when the strategy does not exist
     */
    public function render($uri, $strategy = 'default', array $options = array());

    /**
     * BC support
     *
     * @param array $options
     *
     * @return array
     *
     * @deprecated fixOptions will be removed in 2.3
     */
    public function fixOptions(array $options);
}
