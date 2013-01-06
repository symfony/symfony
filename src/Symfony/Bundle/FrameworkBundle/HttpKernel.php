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
     *
     * @deprecated in 2.2, will be removed in 2.3
     */
    public function forward($controller, array $attributes = array(), array $query = array())
    {
        trigger_error('forward() is deprecated since version 2.2 and will be removed in 2.3.', E_USER_DEPRECATED);

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
     *
     * @deprecated in 2.2, will be removed in 2.3 (use Symfony\Component\HttpKernel\HttpContentRenderer::render() instead)
     */
    public function render($uri, array $options = array())
    {
        trigger_error('render() is deprecated since version 2.2 and will be removed in 2.3. Use Symfony\Component\HttpKernel\HttpContentRenderer::render() instead.', E_USER_DEPRECATED);

        $strategy = isset($options['strategy']) ? $options['strategy'] : 'default';
        unset($options['strategy']);

        $this->container->get('http_content_renderer')->render($uri, $strategy, $options);
    }
}
