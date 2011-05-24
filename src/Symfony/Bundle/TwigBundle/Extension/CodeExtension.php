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

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CodeExtension extends \Twig_Extension
{
    private $container;

    /**
     * Constructor of Twig Extension to provide functions for code formatting
     *
     * @param Symfony\Bundle\FrameworkBundle\Templating\Helper\CodeHelper $helper Helper to use
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            'abbr_class'            => new \Twig_Filter_Method($this, 'abbrClass', array('is_safe' => array('html'))),
            'abbr_method'           => new \Twig_Filter_Method($this, 'abbrMethod', array('is_safe' => array('html'))),
            'format_args'           => new \Twig_Filter_Method($this, 'formatArgs', array('is_safe' => array('html'))),
            'format_args_as_text'   => new \Twig_Filter_Method($this, 'formatArgsAsText'),
            'file_excerpt'          => new \Twig_Filter_Method($this, 'fileExcerpt', array('is_safe' => array('html'))),
            'format_file'           => new \Twig_Filter_Method($this, 'formatFile', array('is_safe' => array('html'))),
            'format_file_from_text' => new \Twig_Filter_Method($this, 'formatFileFromText', array('is_safe' => array('html'))),
            'file_link'             => new \Twig_Filter_Method($this, 'getFileLink', array('is_safe' => array('html'))),
        );
    }

    public function abbrClass($class)
    {
        return $this->container->get('templating.helper.code')->abbrClass($class);
    }

    public function abbrMethod($method)
    {
        return $this->container->get('templating.helper.code')->abbrMethod($method);
    }

    public function formatArgs($args)
    {
        return $this->container->get('templating.helper.code')->formatArgs($args);
    }

    public function formatArgsAsText($args)
    {
        return $this->container->get('templating.helper.code')->formatArgsAsText($args);
    }

    public function fileExcerpt($file, $line)
    {
        return $this->container->get('templating.helper.code')->fileExcerpt($file, $line);
    }

    public function formatFile($file, $line, $text = null)
    {
        return $this->container->get('templating.helper.code')->formatFile($file, $line, $text);
    }

    public function getFileLink($file, $line)
    {
        return $this->container->get('templating.helper.code')->getFileLink($file, $line);
    }

    public function formatFileFromText($text)
    {
        return $this->container->get('templating.helper.code')->formatFileFromText($text);
    }

    public function getName()
    {
        return 'code';
    }
}
