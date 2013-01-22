<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\RenderingStrategy;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Controller\ControllerReference;

/**
 * Implements the default rendering strategy where the Request is rendered by the current HTTP kernel.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DefaultRenderingStrategy extends ProxyAwareRenderingStrategy
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
        if ($uri instanceof ControllerReference) {
            $uri = $this->generateProxyUri($uri, $request);
        }

        $subRequest = $this->createSubRequest($uri, $request);

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

        $subRequest = Request::create($uri, 'get', array(), $cookies, array(), $server);
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
        return 'default';
    }
}
