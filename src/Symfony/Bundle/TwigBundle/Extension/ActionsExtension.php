<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Extension;

use Symfony\Bundle\TwigBundle\TokenParser\RenderTokenParser;
use Symfony\Component\HttpKernel\HttpKernel;

/**
 * Twig extension for Symfony actions helper
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexandre Salomé <alexandre.salome@gmail.com>
 */
class ActionsExtension extends \Twig_Extension
{
    /**
     * @var Symfony\Component\HttpKernel\HttpKernel
     */
    private $kernel;

    public function __construct(HttpKernel $kernel)
    {
        $this->kernel = $kernel;
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
    public function renderAction($controller, array $attributes = array(), array $options = array())
    {
        $options['attributes'] = $attributes;

        return $this->kernel->render($controller, $options);
    }

    /**
     * Returns the token parser instance to add to the existing list.
     *
     * @return array An array of Twig_TokenParser instances
     */
    public function getTokenParsers()
    {
        return array(
            // {% render 'BlogBundle:Post:list' with { 'limit': 2 }, { 'alt': 'BlogBundle:Post:error' } %}
            new RenderTokenParser(),
        );
    }

    public function getName()
    {
        return 'actions';
    }
}
