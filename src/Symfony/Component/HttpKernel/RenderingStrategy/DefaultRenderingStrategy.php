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
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Controller\ControllerReference;

/**
 * Implements the default rendering strategy where the Request is rendered by the current HTTP kernel.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DefaultRenderingStrategy extends GeneratorAwareRenderingStrategy
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
    public function render($uri, Request $request = null, array $options = array())
    {
        if ($uri instanceof ControllerReference) {
            $uri = $this->generateProxyUri($uri, $request);
        }

        $subRequest = $this->createSubRequest($uri, $request);

        $level = ob_get_level();
        try {
            return $this->handle($subRequest);
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
        }
    }

    protected function handle(Request $request)
    {
        $response = $this->kernel->handle($request, HttpKernelInterface::SUB_REQUEST, false);

        if (!$response->isSuccessful()) {
            throw new \RuntimeException(sprintf('Error when rendering "%s" (Status code is %s).', $request->getUri(), $response->getStatusCode()));
        }

        if (!$response instanceof StreamedResponse) {
            return $response->getContent();
        }

        $response->sendContent();
    }

    protected function createSubRequest($uri, Request $request = null)
    {
        if (null !== $request) {
            $cookies = $request->cookies->all();
            $server = $request->server->all();

            // the sub-request is internal
            $server['REMOTE_ADDR'] = '127.0.0.1';
        } else {
            $cookies = array();
            $server = array();
        }

        $subRequest = Request::create($uri, 'get', array(), $cookies, array(), $server);
        if (null !== $request && $session = $request->getSession()) {
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
