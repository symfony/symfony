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
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Controller\ControllerReference;

/**
 * Implements the inline rendering strategy where the Request is rendered by the current HTTP kernel.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class InlineFragmentRenderer extends RoutableFragmentRenderer
{
    private $kernel;

    /**
     * Constructor.
     *
     * @param HttpKernelInterface $kernel A HttpKernelInterface instance
     */
    public function __construct(HttpKernelInterface $kernel)
    {
        $this->kernel = $kernel;
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

            // Remove attributes from the genereated URI because if not, the Symfony
            // routing system will use them to populate the Request attributes. We don't
            // want that as we want to preserve objects (so we manually set Request attributes
            // below instead)
            $attributes = $reference->attributes;
            $reference->attributes = array();
            $uri = $this->generateFragmentUri($uri, $request);
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
            // let's clean up the output buffers that were created by the sub-request
            while (ob_get_level() > $level) {
                ob_get_clean();
            }

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

        // the sub-request is internal
        $server['REMOTE_ADDR'] = '127.0.0.1';

        $subRequest = $request::create($uri, 'get', array(), $cookies, array(), $server);
        if ($request->headers->has('Surrogate-Capability')) {
            $subRequest->headers->set('Surrogate-Capability', $request->headers->get('Surrogate-Capability'));
        }
        if ($session = $request->getSession()) {
            $subRequest->setSession($session);
        }

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
