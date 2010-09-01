<?php

namespace Symfony\Bundle\FrameworkBundle\Templating\Helper;

use Symfony\Component\Templating\Helper\Helper;
use Symfony\Component\OutputEscaper\Escaper;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerResolver;

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
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ActionsHelper extends Helper
{
    protected $resolver;

    /**
     * Constructor.
     *
     * @param Constructor $resolver A ControllerResolver instance
     */
    public function __construct(ControllerResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Outputs the Response content for a given controller.
     *
     * @param string $controller A controller name to execute (a string like BlogBundle:Post:index), or a relative URI
     * @param array  $options    An array of options
     *
     * @see render()
     */
    public function output($controller, array $attributes = array(), array $options = array())
    {
        echo $this->render($controller, $attributes, $options);
    }

    /**
     * Returns the Response content for a given controller or URI.
     *
     * @param string $controller A controller name to execute (a string like BlogBundle:Post:index), or a relative URI
     * @param array  $attributes An array of request attributes
     * @param array  $options    An array of options
     *
     * @see Symfony\Bundle\FrameworkBundle\Controller\ControllerResolver::render()
     */
    public function render($controller, array $attributes = array(), array $options = array())
    {
        $options['attributes'] = Escaper::unescape($attributes);

        if (isset($options['query']))
        {
            $options['query'] = Escaper::unescape($options['query']);
        }

        return $this->resolver->render($controller, $options);
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
