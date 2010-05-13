<?php

namespace Symfony\Framework\WebBundle\Helper;

use Symfony\Components\Templating\Helper\Helper;
use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\OutputEscaper\Escaper;

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
     *
     * @see render()
     */
     public function output($controller, array $path = array(), array $query = array())
     {
         echo $this->render($controller, $path, $query);
     }

    /**
     * Returns the Response content for a given controller.
     *
     * @param string $controller A controller name to execute (a string like BlogBundle:Post:index)
     * @param array  $path       An array of path parameters
     * @param array  $query      An array of query parameters
     */
    public function render($controller, array $path = array(), array $query = array())
    {
        $path['_controller'] = $controller;
        $subRequest = $this->container->getRequestService()->duplicate($query, null, Escaper::unescape($path));

        return $this->container->getKernelService()->handle($subRequest, false, true);
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
