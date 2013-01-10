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
     *  * ignore_errors: true to return an empty string in case of an error
     *  * alt: an alternative URI to execute in case of an error
     *  * standalone: whether to generate an esi:include tag or not when ESI is supported
     *  * comment: a comment to add when returning an esi:include tag
     *
     * @param string $uri     A URI
     * @param array  $options An array of options
     *
     * @return string The Response content
     *
     * @throws \RuntimeException
     * @throws \Exception
     */
    public function render($uri, array $options = array())
    {
        $request = $this->container->get('request');

        $options = array_merge(array(
            'ignore_errors' => !$this->container->getParameter('kernel.debug'),
            'alt'           => null,
            'standalone'    => false,
            'comment'       => '',
            'default'       => null,
        ), $options);

        if (null === $this->esiSupport) {
            $this->esiSupport = $this->container->has('esi') && $this->container->get('esi')->hasSurrogateEsiCapability($request);
        }

        if ($this->esiSupport && (true === $options['standalone'] || 'esi' === $options['standalone'])) {
            return $this->container->get('esi')->renderIncludeTag($uri, $options['alt'], $options['ignore_errors'], $options['comment']);
        }

        if ('js' === $options['standalone']) {
            $defaultContent = null;

            $templating = $this->container->get('templating');

            if ($options['default']) {
                if ($templating->exists($options['default'])) {
                    $defaultContent = $templating->render($options['default']);
                } else {
                    $defaultContent = $options['default'];
                }
            } elseif ($template = $this->container->getParameter('templating.hinclude.default_template')) {
                $defaultContent = $templating->render($template);
            }

            return $this->renderHIncludeTag($uri, $defaultContent);
        }

        $subRequest = Request::create($uri, 'get', array(), $request->cookies->all(), array(), $request->server->all());
        if ($session = $request->getSession()) {
            $subRequest->setSession($session);
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

                return $this->render($alt, $options);
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
     * Renders an HInclude tag.
     *
     * @param string $uri            A URI
     * @param string $defaultContent Default content
     *
     * @return string
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
