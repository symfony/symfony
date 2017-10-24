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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ControllerReference;

/**
 * Renders a URI that represents a resource fragment.
 *
 * This class handles the rendering of resource fragments that are included into
 * a main resource. The handling of the rendering is managed by specialized renderers.
 *
 * This listener works in 2 modes:
 *
 *  * 2.3 compatibility mode where you must call setRequest whenever the Request changes.
 *  * 2.4+ mode where you must pass a RequestStack instance in the constructor.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @see FragmentRendererInterface
 */
class FragmentHandler
{
    private $debug;
    private $renderers = array();
    private $request;
    private $requestStack;

    /**
     * RequestStack will become required in 3.0.
     *
     * @param RequestStack                $requestStack The Request stack that controls the lifecycle of requests
     * @param FragmentRendererInterface[] $renderers    An array of FragmentRendererInterface instances
     * @param bool                        $debug        Whether the debug mode is enabled or not
     */
    public function __construct($requestStack = null, $renderers = array(), $debug = false)
    {
        if (is_array($requestStack)) {
            $tmp = $debug;
            $debug = func_num_args() < 2 ? false : $renderers;
            $renderers = $requestStack;
            $requestStack = func_num_args() < 3 ? null : $tmp;

            @trigger_error('The '.__METHOD__.' method now requires a RequestStack to be given as first argument as '.__CLASS__.'::setRequest method will not be supported anymore in 3.0.', E_USER_DEPRECATED);
        } elseif (!$requestStack instanceof RequestStack) {
            @trigger_error('The '.__METHOD__.' method now requires a RequestStack instance as '.__CLASS__.'::setRequest method will not be supported anymore in 3.0.', E_USER_DEPRECATED);
        }

        if (null !== $requestStack && !$requestStack instanceof RequestStack) {
            throw new \InvalidArgumentException('RequestStack instance expected.');
        }
        if (!is_array($renderers)) {
            throw new \InvalidArgumentException('Renderers must be an array.');
        }

        $this->requestStack = $requestStack;
        foreach ($renderers as $renderer) {
            $this->addRenderer($renderer);
        }
        $this->debug = $debug;
    }

    /**
     * Adds a renderer.
     */
    public function addRenderer(FragmentRendererInterface $renderer)
    {
        $this->renderers[$renderer->getName()] = $renderer;
    }

    /**
     * Sets the current Request.
     *
     * This method was used to synchronize the Request, but as the HttpKernel
     * is doing that automatically now, you should never call it directly.
     * It is kept public for BC with the 2.3 version.
     *
     * @param Request|null $request A Request instance
     *
     * @deprecated since version 2.4, to be removed in 3.0.
     */
    public function setRequest(Request $request = null)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.4 and will be removed in 3.0.', E_USER_DEPRECATED);

        $this->request = $request;
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
     * @throws \LogicException           when no master request is being handled
     */
    public function render($uri, $renderer = 'inline', array $options = array())
    {
        if (!isset($options['ignore_errors'])) {
            $options['ignore_errors'] = !$this->debug;
        }

        if (!isset($this->renderers[$renderer])) {
            throw new \InvalidArgumentException(sprintf('The "%s" renderer does not exist.', $renderer));
        }

        if (!$request = $this->getRequest()) {
            throw new \LogicException('Rendering a fragment can only be done when handling a Request.');
        }

        return $this->deliver($this->renderers[$renderer]->render($uri, $request, $options));
    }

    /**
     * Delivers the Response as a string.
     *
     * When the Response is a StreamedResponse, the content is streamed immediately
     * instead of being returned.
     *
     * @return string|null The Response content or null when the Response is streamed
     *
     * @throws \RuntimeException when the Response is not successful
     */
    protected function deliver(Response $response)
    {
        if (!$response->isSuccessful()) {
            throw new \RuntimeException(sprintf('Error when rendering "%s" (Status code is %s).', $this->getRequest()->getUri(), $response->getStatusCode()));
        }

        if (!$response instanceof StreamedResponse) {
            return $response->getContent();
        }

        $response->sendContent();
    }

    private function getRequest()
    {
        return $this->requestStack ? $this->requestStack->getCurrentRequest() : $this->request;
    }
}
