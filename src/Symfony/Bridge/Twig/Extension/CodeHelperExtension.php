<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Bundle\TwigBundle\Helper\CodeHelper;

/**
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class CodeHelperExtension extends \Twig_Extension
{
    protected $helper;

    public function __construct($fileLinkFormat, $rootDir)
    {
        $this->helper = new CodeHelper($fileLinkFormat, $rootDir);
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
        );
    }

    public function abbrClass($class)
    {
        return $this->helper->abbrClass($class);
    }

    public function abbrMethod($method)
    {
        return $this->helper->abbrMethod($method);
    }

    public function formatArgs($args)
    {
        return $this->helper->formatArgs($args);
    }

    public function formatArgsAsText($args)
    {
        return $this->helper->formatArgsAsText($args);
    }

    public function fileExcerpt($file, $line)
    {
        return $this->helper->fileExcerpt($file, $line);
    }

    public function formatFile($file, $line)
    {
        return $this->helper->formatFile($file, $line);
    }

    public function formatFileFromText($text)
    {
        return $this->helper->formatFileFromText($text);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'code_helpers';
    }
}
