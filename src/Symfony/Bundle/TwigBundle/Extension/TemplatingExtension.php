<?php

namespace Symfony\Bundle\TwigBundle\Extension;

use Symfony\Bundle\TwigBundle\TokenParser\HelperTokenParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\TwigBundle\TokenParser\IncludeTokenParser;
use Symfony\Bundle\TwigBundle\TokenParser\UrlTokenParser;
use Symfony\Bundle\TwigBundle\TokenParser\PathTokenParser;
use Symfony\Bundle\TwigBundle\TokenParser\RenderTokenParser;
use Symfony\Bundle\TwigBundle\TokenParser\StylesheetTokenParser;
use Symfony\Bundle\TwigBundle\TokenParser\StylesheetsTokenParser;
use Symfony\Bundle\TwigBundle\TokenParser\JavascriptTokenParser;
use Symfony\Bundle\TwigBundle\TokenParser\JavascriptsTokenParser;
use Symfony\Component\Yaml\Dumper as YamlDumper;

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
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            'yaml_encode' => new \Twig_Filter_Method($this, 'yamlEncode'),
            'dump' => new \Twig_Filter_Method($this, 'dump'),
            'abbr_class' => new \Twig_Filter_Method($this, 'abbrClass', array('is_safe' => array('html'))),
            'abbr_method' => new \Twig_Filter_Method($this, 'abbrMethod', array('is_safe' => array('html'))),
            'format_args' => new \Twig_Filter_Method($this, 'formatArgs', array('is_safe' => array('html'))),
            'format_args_as_text' => new \Twig_Filter_Method($this, 'formatArgsAsText', array('is_safe' => array('html'))),
            'file_excerpt' => new \Twig_Filter_Method($this, 'fileExcerpt', array('is_safe' => array('html'))),
            'format_file' => new \Twig_Filter_Method($this, 'formatFile', array('is_safe' => array('html'))),
            'format_file_from_text' => new \Twig_Filter_Method($this, 'formatFileFromText', array('is_safe' => array('html'))),
        );
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return array(
            'url'   => new \Twig_Function_Method($this, 'getUrl'),
            'path'  => new \Twig_Function_Method($this, 'getPath'),
            'asset' => new \Twig_Function_Method($this, 'getAssetUrl'),
            'flash' => new \Twig_Function_Method($this, 'getFlash'),
        );
    }

    public function getPath($name, array $parameters = array())
    {
        return $this->container->get('router')->generate($name, $parameters, false);
    }

    public function getUrl($name, array $parameters = array())
    {
        return $this->container->get('router')->generate($name, $parameters, true);
    }

    public function getAssetUrl($location)
    {
        return $this->container->get('templating.helper.assets')->getUrl($location);
    }

    public function getFlash($name)
    {
        return $this->container->get('templating.helper.session')->getFlash($name);
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

            // {% render 'BlogBundle:Post:list' with { 'limit': 2 }, { 'alt': 'BlogBundle:Post:error' } %}
            new RenderTokenParser(),

            // {% include 'sometemplate.php' with { 'something' : 'something2' } %}
            new IncludeTokenParser(),
        );
    }

    public function yamlEncode($input, $inline = 0)
    {
        static $dumper;

        if (null === $dumper) {
            $dumper = new YamlDumper();
        }

        return $dumper->dump($input, $inline);
    }

    public function abbrClass($class)
    {
        return $this->getTemplating()->get('code')->abbrClass($class);
    }

    public function abbrMethod($method)
    {
        return $this->getTemplating()->get('code')->abbrMethod($method);
    }

    public function formatArgs($args)
    {
        return $this->getTemplating()->get('code')->formatArgs($args);
    }

    public function formatArgsAsText($args)
    {
        return $this->getTemplating()->get('code')->formatArgsAsText($args);
    }

    public function fileExcerpt($file, $line)
    {
        return $this->getTemplating()->get('code')->fileExcerpt($file, $line);
    }

    public function formatFile($file, $line)
    {
        return $this->getTemplating()->get('code')->formatFile($file, $line);
    }

    public function formatFileFromText($text)
    {
        return $this->getTemplating()->get('code')->formatFileFromText($text);
    }

    public function dump($value)
    {
        if (is_resource($value)) {
            return '%Resource%';
        }

        if (is_array($value) || is_object($value)) {
            return '%'.gettype($value).'% '.$this->yamlEncode($value);
        }

        return $value;
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
