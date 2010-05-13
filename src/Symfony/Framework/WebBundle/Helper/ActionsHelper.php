<?php

namespace Symfony\Framework\WebBundle\Helper;

use Symfony\Components\Templating\Helper\Helper;
use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\OutputEscaper\Escaper;
use Symfony\Components\HttpKernel\HttpKernelInterface;

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
     * @param string $controller A controller name to execute (a string like BlogBundle:Post:index)
     * @param array  $path       An array of path parameters
     * @param array  $query      An array of query parameters
     * @param array  $options    An array of options
     *
     * @see render()
     */
    public function output($controller, array $path = array(), array $query = array(), array $options = array())
    {
        echo $this->render($controller, $path, $query, $options);
    }

    /**
     * Returns the Response content for a given controller.
     *
     * Available options:
     *
     *  * ignore_errors: true to return an empty string in case of an error
     *  * alt: an alternative controller to execute in case of an error (an array with the controller, the path arguments, the query arguments)
     *
     * @param string $controller A controller name to execute (a string like BlogBundle:Post:index)
     * @param array  $path       An array of path parameters
     * @param array  $query      An array of query parameters
     * @param array  $options    An array of options
     */
    public function render($controller, array $path = array(), array $query = array(), array $options = array())
    {
        $options = array_merge(array(
            'ignore_errors' => true,
            'alt'           => array(),
        ), $options);

        if (!is_array($options['alt']))
        {
            $options['alt'] = array($options['alt']);
        }

        $path = Escaper::unescape($path);
        $query = Escaper::unescape($query);

        $request = $this->container->getRequestService();
        $path['_controller'] = $controller;
        $path['_format'] = $request->getRequestFormat();
        $subRequest = $request->duplicate($query, null, $path);

        try {
            return $this->container->getKernelService()->handle($subRequest, HttpKernelInterface::EMBEDDED_REQUEST, true);
        } catch (\Exception $e) {
            if ($options['alt']) {
                $alt = $options['alt'];
                unset($options['alt']);

                return $this->render($alt[0], isset($alt[1]) ? $alt[1] : array(), isset($alt[2]) ? $alt[2] : array(), $options);
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
