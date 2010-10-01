<?php

namespace Symfony\Bundle\TwigBundle\Extension;

use Symfony\Bundle\TwigBundle\TokenParser\HelperTokenParser;
use Symfony\Component\DependencyInjection\ContainerInterface;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class HelpersExtension extends \Twig_Extension
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Returns the token parser instance to add to the existing list.
     *
     * @return array An array of Twig_TokenParser instances
     */
    public function getTokenParsers()
    {
        return array(
            // {% javascript 'bundles/blog/js/blog.js' %}
            new HelperTokenParser('javascript', '<js> [with <arguments:array>]', 'templating.helper.javascripts', 'add'),

            // {% javascripts %}
            new HelperTokenParser('javascripts', '', 'templating.helper.javascripts', 'render'),

            // {% stylesheet 'bundles/blog/css/blog.css' with ['media': 'screen'] %}
            new HelperTokenParser('stylesheet', '<css> [with <arguments:array>]', 'templating.helper.stylesheets', 'add'),

            // {% stylesheets %}
            new HelperTokenParser('stylesheets', '', 'templating.helper.stylesheets', 'render'),

            // {% asset 'css/blog.css' %}
            new HelperTokenParser('asset', '<location>', 'templating.helper.assets', 'getUrl'),

            // {% route 'blog_post' with ['id': post.id] %}
            new HelperTokenParser('route', '<route> [with <arguments:array>]', 'templating.helper.router', 'generate'),

            // {% render 'BlogBundle:Post:list' with ['limit': 2], ['alt': 'BlogBundle:Post:error'] %}
            new HelperTokenParser('render', '<template> [with <attributes:array>[, <options:array>]]', 'templating.helper.actions', 'render'),

            // {% flash 'notice' %}
            new HelperTokenParser('flash', '<name>', 'templating.helper.session', 'flash'),
        );
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'symfony.helpers';
    }
}
