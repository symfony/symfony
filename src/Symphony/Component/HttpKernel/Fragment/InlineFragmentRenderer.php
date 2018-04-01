<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Fragment;

use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpKernel\HttpKernelInterface;
use Symphony\Component\HttpKernel\Controller\ControllerReference;
use Symphony\Component\HttpKernel\KernelEvents;
use Symphony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symphony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Implements the inline rendering strategy where the Request is rendered by the current HTTP kernel.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class InlineFragmentRenderer extends RoutableFragmentRenderer
{
    private $kernel;
    private $dispatcher;

    public function __construct(HttpKernelInterface $kernel, EventDispatcherInterface $dispatcher = null)
    {
        $this->kernel = $kernel;
        $this->dispatcher = $dispatcher;
    }

    /**
     * {@inheritdoc}
     *
     * Additional available options:
     *
     *  * alt: an alternative URI to render in case of an error
     */
    public function render($uri, Request $request, array $options = array())
    {
        $reference = null;
        if ($uri instanceof ControllerReference) {
            $reference = $uri;

            // Remove attributes from the generated URI because if not, the Symphony
            // routing system will use them to populate the Request attributes. We don't
            // want that as we want to preserve objects (so we manually set Request attributes
            // below instead)
            $attributes = $reference->attributes;
            $reference->attributes = array();

            // The request format and locale might have been overridden by the user
            foreach (array('_format', '_locale') as $key) {
                if (isset($attributes[$key])) {
                    $reference->attributes[$key] = $attributes[$key];
                }
            }

            $uri = $this->generateFragmentUri($uri, $request, false, false);

            $reference->attributes = array_merge($attributes, $reference->attributes);
        }

        $subRequest = $this->createSubRequest($uri, $request);

        // override Request attributes as they can be objects (which are not supported by the generated URI)
        if (null !== $reference) {
            $subRequest->attributes->add($reference->attributes);
        }

        $level = ob_get_level();
        try {
            return $this->kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);
        } catch (\Exception $e) {
            // we dispatch the exception event to trigger the logging
            // the response that comes back is simply ignored
            if (isset($options['ignore_errors']) && $options['ignore_errors'] && $this->dispatcher) {
                $event = new GetResponseForExceptionEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST, $e);

                $this->dispatcher->dispatch(KernelEvents::EXCEPTION, $event);
            }

            // let's clean up the output buffers that were created by the sub-request
            Response::closeOutputBuffers($level, false);

            if (isset($options['alt'])) {
                $alt = $options['alt'];
                unset($options['alt']);

                return $this->render($alt, $request, $options);
            }

            if (!isset($options['ignore_errors']) || !$options['ignore_errors']) {
                throw $e;
            }

            return new Response();
        }
    }

    protected function createSubRequest($uri, Request $request)
    {
        $cookies = $request->cookies->all();
        $server = $request->server->all();

        if (Request::HEADER_X_FORWARDED_FOR & Request::getTrustedHeaderSet()) {
            $currentXForwardedFor = $request->headers->get('X_FORWARDED_FOR', '');

            $server['HTTP_X_FORWARDED_FOR'] = ($currentXForwardedFor ? $currentXForwardedFor.', ' : '').$request->getClientIp();
        }

        $server['REMOTE_ADDR'] = '127.0.0.1';
        unset($server['HTTP_IF_MODIFIED_SINCE']);
        unset($server['HTTP_IF_NONE_MATCH']);

        $subRequest = Request::create($uri, 'get', array(), $cookies, array(), $server);
        if ($request->headers->has('Surrogate-Capability')) {
            $subRequest->headers->set('Surrogate-Capability', $request->headers->get('Surrogate-Capability'));
        }

        static $setSession;

        if (null === $setSession) {
            $setSession = \Closure::bind(function ($subRequest, $request) { $subRequest->session = $request->session; }, null, Request::class);
        }
        $setSession($subRequest, $request);

        return $subRequest;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'inline';
    }
}
