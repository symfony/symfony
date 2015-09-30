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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Fragment\FragmentHandler;

/**
 * Twig extension for Symfony actions helper.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 2.2, to be removed in 3.0.
 */
class ActionsExtension extends \Twig_Extension
{
    private $handler;

    /**
     * @param FragmentHandler|ContainerInterface $handler
     *
     * @deprecated Passing a ContainerInterface as a first argument is deprecated since 2.7 and will be removed in 3.0.
     */
    public function __construct($handler)
    {
        if ($handler instanceof FragmentHandler) {
            $this->handler = $handler;
        } elseif ($handler instanceof ContainerInterface) {
            @trigger_error('The ability to pass a ContainerInterface instance as a first argument to '.__METHOD__.' method is deprecated since version 2.7 and will be removed in 3.0. Pass a FragmentHandler instance instead.', E_USER_DEPRECATED);

            $this->handler = $handler->get('fragment.handler');
        } else {
            throw new \BadFunctionCallException(sprintf('%s takes a FragmentHandler or a ContainerInterface object as its first argument.', __METHOD__));
        }

        $this->handler = $handler;
    }

    /**
     * Returns the Response content for a given URI.
     *
     * @param string $uri     A URI
     * @param array  $options An array of options
     *
     * @see FragmentHandler::render()
     */
    public function renderUri($uri, array $options = array())
    {
        @trigger_error('The Twig render tag was deprecated in version 2.2 and will be removed in version 3.0. Use the Twig render function instead.', E_USER_DEPRECATED);

        $strategy = isset($options['strategy']) ? $options['strategy'] : 'inline';
        unset($options['strategy']);

        return $this->handler->render($uri, $strategy, $options);
    }

    /**
     * Returns the token parser instance to add to the existing list.
     *
     * @return array An array of \Twig_TokenParser instances
     */
    public function getTokenParsers()
    {
        return array(
            // {% render url('post_list', { 'limit': 2 }), { 'alt': 'BlogBundle:Post:error' } %}
            new RenderTokenParser(),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'actions';
    }
}
