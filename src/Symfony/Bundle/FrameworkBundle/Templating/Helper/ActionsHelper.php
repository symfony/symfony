<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating\Helper;

use Symfony\Component\Templating\Helper\Helper;
use Symfony\Component\HttpKernel\HttpContentRenderer;
use Symfony\Component\HttpKernel\Controller\ControllerReference;

/**
 * ActionsHelper manages action inclusions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ActionsHelper extends Helper
{
    private $renderer;

    /**
     * Constructor.
     *
     * @param HttpContentRenderer $kernel A HttpContentRenderer instance
     */
    public function __construct(HttpContentRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * Returns the Response content for a given URI.
     *
     * @param string $uri     A URI
     * @param array  $options An array of options
     *
     * @return string
     *
     * @see Symfony\Component\HttpKernel\HttpContentRenderer::render()
     */
    public function render($uri, array $options = array())
    {
        return $this->renderer->render($uri, $options);
    }

    public function controller($controller, $attributes = array(), $query = array())
    {
        return new ControllerReference($controller, $attributes, $query);
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
