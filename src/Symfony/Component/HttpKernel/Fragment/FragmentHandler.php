<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Fragment;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Renders a URI that represents a resource fragment.
 *
 * This class handles the rendering of resource fragments that are included into
 * a main resource. The handling of the rendering is managed by specialized renderers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @see FragmentRendererInterface
 */
class FragmentHandler implements EventSubscriberInterface
{
    private $debug;
    private $renderers;
    private $requests;

    /**
     * Constructor.
     *
     * @param FragmentRendererInterface[] $renderers An array of FragmentRendererInterface instances
     * @param Boolean                     $debug     Whether the debug mode is enabled or not
     */
    public function __construct(array $renderers = array(), $debug = false)
    {
        $this->renderers = array();
        foreach ($renderers as $renderer) {
            $this->addRenderer($renderer);
        }
        $this->debug = $debug;
        $this->requests = array();
    }

    /**
     * Adds a renderer.
     *
     * @param FragmentRendererInterface $strategy A FragmentRendererInterface instance
     */
    public function addRenderer(FragmentRendererInterface $renderer)
    {
        $this->renderers[$renderer->getName()] = $renderer;
    }

    /**
     * Stores the Request object.
     *
     * @param GetResponseEvent $event A GetResponseEvent instance
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        array_unshift($this->requests, $event->getRequest());
    }

    /**
     * Removes the most recent Request object.
     *
     * @param FilterResponseEvent $event A FilterResponseEvent instance
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        array_shift($this->requests);
    }

    /**
     * Renders a URI and returns the Response content.
     *
     * Available options:
     *
     *  * ignore_errors: true to return an empty string in case of an error
     *
     * @param string|ControllerReference $uri      A URI as a string or a ControllerReference instance
     * @param string                     $renderer The renderer name
     * @param array                      $options  An array of options
     *
     * @return string|null The Response content or null when the Response is streamed
     *
     * @throws \InvalidArgumentException when the renderer does not exist
     * @throws \RuntimeException         when the Response is not successful
     */
    public function render($uri, $renderer = 'inline', array $options = array())
    {
        if (!isset($options['ignore_errors'])) {
            $options['ignore_errors'] = !$this->debug;
        }

        if (!isset($this->renderers[$renderer])) {
            throw new \InvalidArgumentException(sprintf('The "%s" renderer does not exist.', $renderer));
        }

        return $this->deliver($this->renderers[$renderer]->render($uri, $this->requests[0], $options));
    }

    /**
     * Delivers the Response as a string.
     *
     * When the Response is a StreamedResponse, the content is streamed immediately
     * instead of being returned.
     *
     * @param Response $response A Response instance
     *
     * @return string|null The Response content or null when the Response is streamed
     *
     * @throws \RuntimeException when the Response is not successful
     */
    protected function deliver(Response $response)
    {
        if (!$response->isSuccessful()) {
            throw new \RuntimeException(sprintf('Error when rendering "%s" (Status code is %s).', $this->requests[0]->getUri(), $response->getStatusCode()));
        }

        if (!$response instanceof StreamedResponse) {
            return $response->getContent();
        }

        $response->sendContent();
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST  => 'onKernelRequest',
            KernelEvents::RESPONSE => 'onKernelResponse',
        );
    }

    // to be removed in 2.3
    public function fixOptions(array $options)
    {
        // support for the standalone option is @deprecated in 2.2 and replaced with the renderer option
        if (isset($options['standalone'])) {
            trigger_error('The "standalone" option is deprecated in version 2.2 and replaced with the "renderer" option.', E_USER_DEPRECATED);

            // support for the true value is @deprecated in 2.2, will be removed in 2.3
            if (true === $options['standalone']) {
                trigger_error('The "true" value for the "standalone" option is deprecated in version 2.2 and replaced with the "esi" value.', E_USER_DEPRECATED);

                $options['standalone'] = 'esi';
            } elseif (false === $options['standalone']) {
                trigger_error('The "false" value for the "standalone" option is deprecated in version 2.2 and replaced with the "inline" value.', E_USER_DEPRECATED);

                $options['standalone'] = 'inline';
            } elseif ('js' === $options['standalone']) {
                trigger_error('The "js" value for the "standalone" option is deprecated in version 2.2 and replaced with the "hinclude" value.', E_USER_DEPRECATED);

                $options['standalone'] = 'hinclude';
            }

            $options['renderer'] = $options['standalone'];
            unset($options['standalone']);
        }

        return $options;
    }
}
