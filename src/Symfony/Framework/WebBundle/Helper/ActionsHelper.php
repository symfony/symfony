<?php

namespace Symfony\Framework\WebBundle\Helper;

use Symfony\Components\Templating\Helper\Helper;
use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\OutputEscaper\Escaper;
use Symfony\Components\HttpKernel\HttpKernelInterface;
use Symfony\Components\HttpKernel\Request;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ActionsHelper.
 *
 * @package    Symfony
 * @subpackage Framework_WebBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ActionsHelper extends Helper
{
    protected $container;

    /**
     * Constructor.
     *
     * @param Constructor $container A ContainerInterface instance
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Outputs the Response content for a given controller.
     *
     * @param string $controller A controller name to execute (a string like BlogBundle:Post:index), or a relative URI
     * @param array  $options    An array of options
     *
     * @see render()
     */
    public function output($controller, array $options = array())
    {
        echo $this->render($controller, $options);
    }

    /**
     * Returns the Response content for a given controller or URI.
     *
     * Available options:
     *
     *  * path: An array of path parameters (only when the first argument is a controller)
     *  * query: An array of query parameters (only when the first argument is a controller)
     *  * ignore_errors: true to return an empty string in case of an error
     *  * alt: an alternative controller to execute in case of an error (an array with the controller, the path arguments, the query arguments)
     *
     * @param string $controller A controller name to execute (a string like BlogBundle:Post:index), or a relative URI
     * @param array  $options    An array of options
     */
    public function render($controller, array $options = array())
    {
        $options = array_merge(array(
            'path'          => array(),
            'query'         => array(),
            'ignore_errors' => true,
            'alt'           => array(),
        ), $options);

        if (!is_array($options['alt'])) {
            $options['alt'] = array($options['alt']);
        }

        $options['path'] = Escaper::unescape($options['path']);
        $options['query'] = Escaper::unescape($options['query']);

        // controller or URI?
        $request = $this->container->getRequestService();
        if (0 === strpos($controller, '/')) {
            // URI
            $subRequest = Request::create($controller, 'get', array(), $request->cookies->all(), array(), $request->server->all());
        } else {
            // controller
            $options['path']['_controller'] = $controller;
            $options['path']['_format'] = $request->getRequestFormat();
            $subRequest = $request->duplicate($options['query'], null, $options['path']);
        }

        try {
            return $this->container->getKernelService()->handle($subRequest, HttpKernelInterface::EMBEDDED_REQUEST, true);
        } catch (\Exception $e) {
            if ($options['alt']) {
                $alt = $options['alt'];
                unset($options['alt']);
                $options['path'] = isset($alt[1]) ? $alt[1] : array();
                $options['query'] = isset($alt[2]) ? $alt[2] : array();

                return $this->render($alt[0], $options);
            }

            if (!$options['ignore_errors']) {
                throw $e;
            }
        }
    }

    /**
     * Returns the canonical name of this helper.
     *
     * @return string The canonical name
     */
    public function getName()
    {
        return 'actions';
    }
}
