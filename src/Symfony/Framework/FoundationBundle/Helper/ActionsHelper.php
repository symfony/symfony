<?php

namespace Symfony\Framework\FoundationBundle\Helper;

use Symfony\Components\Templating\Helper\Helper;
use Symfony\Components\OutputEscaper\Escaper;
use Symfony\Framework\FoundationBundle\Controller\ControllerManager;

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
 * @subpackage Framework_FoundationBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ActionsHelper extends Helper
{
    protected $manager;

    /**
     * Constructor.
     *
     * @param Constructor $container A ContainerInterface instance
     */
    public function __construct(ControllerManager $manager)
    {
        $this->manager = $manager;
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
     * @param string $controller A controller name to execute (a string like BlogBundle:Post:index), or a relative URI
     * @param array  $options    An array of options
     *
     * @see Symfony\Framework\FoundationBundle\Controller\ControllerManager::render()
     */
    public function render($controller, array $options = array())
    {
        if (isset($options['path']))
        {
            $options['path'] = Escaper::unescape($options['path']);
        }

        if (isset($options['query']))
        {
            $options['query'] = Escaper::unescape($options['query']);
        }

        return $this->manager->render($controller, $options);
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
