<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\HttpKernel as BaseHttpKernel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * This HttpKernel is used to manage scope changes of the DI container.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class HttpKernel extends BaseHttpKernel
{
    protected $container;

    private $esiSupport;

    public function __construct(EventDispatcherInterface $dispatcher, ContainerInterface $container, ControllerResolverInterface $controllerResolver)
    {
        parent::__construct($dispatcher, $controllerResolver);

        $this->container = $container;
    }

    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        $request->headers->set('X-Php-Ob-Level', ob_get_level());

        $this->container->enterScope('request');
        $this->container->set('request', $request, 'request');

        try {
            $response = parent::handle($request, $type, $catch);
        } catch (\Exception $e) {
            $this->container->leaveScope('request');

            throw $e;
        }

        $this->container->leaveScope('request');

        return $response;
    }

    /**
     * Forwards the request to another controller.
     *
     * @param string $controller The controller name (a string like BlogBundle:Post:index)
     * @param array  $attributes An array of request attributes
     * @param array  $query      An array of request query parameters
     *
     * @return Response A Response instance
     */
    public function forward($controller, array $attributes = array(), array $query = array())
    {
        $attributes['_controller'] = $controller;
        $subRequest = $this->container->get('request')->duplicate($query, null, $attributes);

        return $this->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }

    /**
     * Renders a Controller and returns the Response content.
     *
     * Note that this method generates an esi:include tag only when both the standalone
     * option is set to true and the request has ESI capability (@see Symfony\Component\HttpKernel\HttpCache\ESI).
     *
     * Available options:
     *
     *  * attributes: An array of request attributes (only when the first argument is a controller)
     *  * query: An array of request query parameters (only when the first argument is a controller)
     *  * ignore_errors: true to return an empty string in case of an error
     *  * alt: an alternative controller to execute in case of an error (can be a controller, a URI, or an array with the controller, the attributes, and the query arguments)
     *  * standalone: whether to generate an esi:include tag or not when ESI is supported
     *  * comment: a comment to add when returning an esi:include tag
     *
     * @param string $controller A controller name to execute (a string like BlogBundle:Post:index), or a relative URI
     * @param array  $options    An array of options
     *
     * @return string The Response content
     */
    public function render($controller, array $options = array())
    {
        $options = array_merge(array(
            'attributes'    => array(),
            'query'         => array(),
            'ignore_errors' => !$this->container->getParameter('kernel.debug'),
            'alt'           => array(),
            'standalone'    => false,
            'comment'       => '',
        ), $options);

        if (!is_array($options['alt'])) {
            $options['alt'] = array($options['alt']);
        }

        if (null === $this->esiSupport) {
            $this->esiSupport = $this->container->has('esi') && $this->container->get('esi')->hasSurrogateEsiCapability($this->container->get('request'));
        }

        if ($this->esiSupport && (true === $options['standalone'] || 'esi' === $options['standalone'])) {
            if (0 === strpos($controller, 'http://') || 0 === strpos($controller, 'https://')) {
                $uri = $controller;
            } else {
                $uri = $this->generateInternalUri($controller, $options['attributes'], $options['query']);
            }

            $alt = '';
            if ($options['alt']) {
                if (is_string($options['alt']) && (0 === strpos($options['alt'], 'http://') || 0 === strpos($options['alt'], 'https://'))) {
                    $alt = $options['alt'];
                } else {
                    $alt = $this->generateInternalUri($options['alt'][0], isset($options['alt'][1]) ? $options['alt'][1] : array(), isset($options['alt'][2]) ? $options['alt'][2] : array());
                }
            }

            return $this->container->get('esi')->renderIncludeTag($uri, $alt, $options['ignore_errors'], $options['comment']);
        }

        if ('js' === $options['standalone']) {
            if (0 === strpos($controller, 'http://') || 0 === strpos($controller, 'https://')) {
                $uri = $controller;
            } else {
                $uri = $this->generateInternalUri($controller, $options['attributes'], $options['query'], false);
            }
            $defaultContent = null;

            if ($template = $this->container->getParameter('templating.hinclude.default_template')) {
                $defaultContent = $this->container->get('templating')->render($template);
            }

            return $this->renderHIncludeTag($uri, $defaultContent);
        }

        $request = $this->container->get('request');

        // controller or URI or path?
        if (0 === strpos($controller, 'http://') || 0 === strpos($controller, 'https://')) {
            $subRequest = $request::create($controller, 'get', array(), $request->cookies->all(), array(), $request->server->all());
            if ($session = $request->getSession()) {
                $subRequest->setSession($session);
            }
        } elseif (0 === strpos($controller, '/')) {
            $subRequest = $request::create($request->getUriForPath($controller), 'get', array(), $request->cookies->all(), array(), $request->server->all());
            if ($session = $request->getSession()) {
                $subRequest->setSession($session);
            }
        } else {
            $options['attributes']['_controller'] = $controller;

            if (!isset($options['attributes']['_format'])) {
                $options['attributes']['_format'] = $request->getRequestFormat();
            }

            $options['attributes']['_route'] = '_internal';
            $subRequest = $request->duplicate($options['query'], null, $options['attributes']);
            $subRequest->setMethod('GET');
        }

        $level = ob_get_level();
        try {
            $response = $this->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);

            if (!$response->isSuccessful()) {
                throw new \RuntimeException(sprintf('Error when rendering "%s" (Status code is %s).', $request->getUri(), $response->getStatusCode()));
            }

            if (!$response instanceof StreamedResponse) {
                return $response->getContent();
            }

            $response->sendContent();
        } catch (\Exception $e) {
            if ($options['alt']) {
                $alt = $options['alt'];
                unset($options['alt']);
                $options['attributes'] = isset($alt[1]) ? $alt[1] : array();
                $options['query'] = isset($alt[2]) ? $alt[2] : array();

                return $this->render($alt[0], $options);
            }

            if (!$options['ignore_errors']) {
                throw $e;
            }

            // let's clean up the output buffers that were created by the sub-request
            while (ob_get_level() > $level) {
                ob_get_clean();
            }
        }
    }

    /**
     * Generates an internal URI for a given controller.
     *
     * This method uses the "_internal" route, which should be available.
     *
     * @param string  $controller A controller name to execute (a string like BlogBundle:Post:index), or a relative URI
     * @param array   $attributes An array of request attributes
     * @param array   $query      An array of request query parameters
     * @param boolean $secure
     *
     * @return string An internal URI
     */
    public function generateInternalUri($controller, array $attributes = array(), array $query = array(), $secure = true)
    {
        if (0 === strpos($controller, '/')) {
            return $controller;
        }

        $path = http_build_query($attributes, '', '&');
        $uri = $this->container->get('router')->generate($secure ? '_internal' : '_internal_public', array(
            'controller' => $controller,
            'path'       => $path ?: 'none',
            '_format'    => $this->container->get('request')->getRequestFormat(),
        ));

        if ($queryString = http_build_query($query, '', '&')) {
            $uri .= '?'.$queryString;
        }

        return $uri;
    }

    /**
     * Renders an HInclude tag.
     *
     * @param string $uri A URI
     * @param string $defaultContent Default content
     */
    public function renderHIncludeTag($uri, $defaultContent = null)
    {
        return sprintf('<hx:include src="%s">%s</hx:include>', $uri, $defaultContent);
    }

    public function hasEsiSupport()
    {
        return $this->esiSupport;
    }
}
