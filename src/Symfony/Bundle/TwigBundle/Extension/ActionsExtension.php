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

/**
 * Twig extension for Symfony actions helper
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.0.0
 */
class ActionsExtension extends \Twig_Extension
{
    private $container;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container The service container
     *
     * @since v2.0.0
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Returns the Response content for a given URI.
     *
     * @param string $uri     A URI
     * @param array  $options An array of options
     *
     * @see Symfony\Bundle\FrameworkBundle\Controller\ControllerResolver::render()
     *
     * @since v2.2.0
     */
    public function renderUri($uri, array $options = array())
    {
        return $this->container->get('templating.helper.actions')->render($uri, $options);
    }

    /**
     * Returns the token parser instance to add to the existing list.
     *
     * @return array An array of Twig_TokenParser instances
     *
     * @since v2.0.0
     */
    public function getTokenParsers()
    {
        return array(
            // {% render url('post_list', { 'limit': 2 }), { 'alt': 'BlogBundle:Post:error' } %}
            new RenderTokenParser(),
        );
    }

    /**
     * @since v2.0.0
     */
    public function getName()
    {
        return 'actions';
    }
}
