<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\CompatAssetsBundle\Twig\Extension;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\CompatAssetsBundle\Twig\TokenParser\StylesheetTokenParser;
use Symfony\Bundle\CompatAssetsBundle\Twig\TokenParser\StylesheetsTokenParser;
use Symfony\Bundle\CompatAssetsBundle\Twig\TokenParser\JavascriptTokenParser;
use Symfony\Bundle\CompatAssetsBundle\Twig\TokenParser\JavascriptsTokenParser;

/**
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class AssetsExtension extends \Twig_Extension
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

    public function getTemplating()
    {
        return $this->container->get('templating.engine');
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
            new JavascriptTokenParser(),

            // {% javascripts %}
            new JavascriptsTokenParser(),

            // {% stylesheet 'bundles/blog/css/blog.css' with { 'media': 'screen' } %}
            new StylesheetTokenParser(),

            // {% stylesheets %}
            new StylesheetsTokenParser(),
        );
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'assets';
    }
}
