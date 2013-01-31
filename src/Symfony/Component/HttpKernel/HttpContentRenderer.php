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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\RenderingStrategy\RenderingStrategyInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Renders a URI using different strategies.
 *
 * This class handles sub-requests. The response content from the sub-request
 * is then embedded into a master request. The handling of the sub-request
 * is managed by rendering strategies.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @see RenderingStrategyInterface
 */
class HttpContentRenderer implements EventSubscriberInterface
{
    private $debug;
    private $strategies;
    private $requests;

    /**
     * Constructor.
     *
     * @param RenderingStrategyInterface[] $strategies An array of RenderingStrategyInterface instances
     * @param Boolean                      $debug      Whether the debug mode is enabled or not
     */
    public function __construct(array $strategies = array(), $debug = false)
    {
        $this->strategies = array();
        foreach ($strategies as $strategy) {
            $this->addStrategy($strategy);
        }
        $this->debug = $debug;
        $this->requests = array();
    }

    /**
     * Adds a rendering strategy.
     *
     * @param RenderingStrategyInterface $strategy A RenderingStrategyInterface instance
     */
    public function addStrategy(RenderingStrategyInterface $strategy)
    {
        $this->strategies[$strategy->getName()] = $strategy;
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
     * @param string                     $strategy The strategy to use for the rendering
     * @param array                      $options  An array of options
     *
     * @return string|null The Response content or null when the Response is streamed
     *
     * @throws \InvalidArgumentException when the strategy does not exist
     * @throws \RuntimeException         when the Response is not successful
     */
    public function render($uri, $strategy = 'default', array $options = array())
    {
        if (!isset($options['ignore_errors'])) {
            $options['ignore_errors'] = !$this->debug;
        }

        if (!isset($this->strategies[$strategy])) {
            throw new \InvalidArgumentException(sprintf('The "%s" rendering strategy does not exist.', $strategy));
        }

        return $this->deliver($this->strategies[$strategy]->render($uri, $this->requests[0], $options));
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
        // support for the standalone option is @deprecated in 2.2 and replaced with the strategy option
        if (isset($options['standalone'])) {
            trigger_error('The "standalone" option is deprecated in version 2.2 and replaced with the "strategy" option.', E_USER_DEPRECATED);

            // support for the true value is @deprecated in 2.2, will be removed in 2.3
            if (true === $options['standalone']) {
                trigger_error('The "true" value for the "standalone" option is deprecated in version 2.2 and replaced with the "esi" value.', E_USER_DEPRECATED);

                $options['standalone'] = 'esi';
            } elseif (false === $options['standalone']) {
                trigger_error('The "false" value for the "standalone" option is deprecated in version 2.2 and replaced with the "default" value.', E_USER_DEPRECATED);

                $options['standalone'] = 'default';
            } elseif ('js' === $options['standalone']) {
                trigger_error('The "js" value for the "standalone" option is deprecated in version 2.2 and replaced with the "hinclude" value.', E_USER_DEPRECATED);

                $options['standalone'] = 'hinclude';
            }

            $options['strategy'] = $options['standalone'];
            unset($options['standalone']);
        }

        return $options;
    }
}
