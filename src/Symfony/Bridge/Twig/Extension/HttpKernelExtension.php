<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\HttpKernel\HttpContentRenderer;
use Symfony\Component\HttpKernel\Controller\ControllerReference;

/**
 * Provides integration with the HttpKernel component.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HttpKernelExtension extends \Twig_Extension
{
    private $renderer;

    /**
     * Constructor.
     *
     * @param HttpContentRenderer $renderer A HttpContentRenderer instance
     */
    public function __construct(HttpContentRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public function getFunctions()
    {
        return array(
            'render' => new \Twig_Function_Method($this, 'render', array('is_safe' => array('html'))),
            'render_*' => new \Twig_Function_Method($this, 'renderStrategy', array('is_safe' => array('html'))),
            'controller' => new \Twig_Function_Method($this, 'controller'),
        );
    }

    /**
     * Renders a URI.
     *
     * @param string $uri     A URI
     * @param array  $options An array of options
     *
     * @return string The Response content
     *
     * @see Symfony\Component\HttpKernel\HttpContentRenderer::render()
     */
    public function render($uri, $options = array())
    {
        $options = $this->renderer->fixOptions($options);

        $strategy = isset($options['strategy']) ? $options['strategy'] : 'default';
        unset($options['strategy']);

        return $this->renderer->render($uri, $strategy, $options);
    }

    public function renderStrategy($strategy, $uri, $options = array())
    {
        return $this->renderer->render($uri, $strategy, $options);
    }

    public function controller($controller, $attributes = array(), $query = array())
    {
        return new ControllerReference($controller, $attributes, $query);
    }

    public function getName()
    {
        return 'http_kernel';
    }
}
