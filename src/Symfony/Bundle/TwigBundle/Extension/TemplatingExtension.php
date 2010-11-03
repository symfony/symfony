<?php

namespace Symfony\Bundle\TwigBundle\Extension;

use Symfony\Bundle\TwigBundle\TokenParser\HelperTokenParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\TwigBundle\TokenParser\IncludeTokenParser;
use Symfony\Bundle\TwigBundle\TokenParser\UrlTokenParser;
use Symfony\Bundle\TwigBundle\TokenParser\PathTokenParser;

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
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class TemplatingExtension extends \Twig_Extension
{
    protected $container;
    protected $templating;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->templating = $container->get('templating.engine');
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function getTemplating()
    {
        return $this->templating;
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

            // {% render 'BlogBundle:Post:list' with ['limit': 2], ['alt': 'BlogBundle:Post:error'] %}
            new HelperTokenParser('render', '<template> [with <attributes:array>[, <options:array>]]', 'templating.helper.actions', 'render'),

            // {% flash 'notice' %}
            new HelperTokenParser('flash', '<name>', 'templating.helper.session', 'getFlash'),

            // {% path 'blog_post' with ['id': post.id] %}
            new PathTokenParser(),

            // {% url 'blog_post' with ['id': post.id] %}
            new UrlTokenParser(),

            // {% include 'sometemplate.php' with ['something' : 'something2'] %}
            new IncludeTokenParser(),
        );
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'templating';
    }
}
