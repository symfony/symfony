<?php

namespace Symfony\Component\Templating\Helper;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * StylesheetsHelper is a helper that manages stylesheets.
 *
 * Usage:
 *
 * <code>
 *   $view['stylesheets']->add('foo.css', array('media' => 'print'));
 *   echo $view['stylesheets'];
 * </code>
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class StylesheetsHelper extends Helper
{
    protected $stylesheets = array();
    protected $assetHelper;

    /**
     * Constructor.
     *
     * @param AssetsHelper $assetHelper A AssetsHelper instance
     */
    public function __construct(AssetsHelper $assetHelper)
    {
        $this->assetHelper = $assetHelper;
    }

    /**
     * Adds a stylesheets file.
     *
     * @param string $stylesheet A stylesheet file path
     * @param array  $attributes An array of attributes
     */
    public function add($stylesheet, $attributes = array())
    {
        $this->stylesheets[$this->assetHelper->getUrl($stylesheet)] = $attributes;
    }

    /**
     * Returns all stylesheet files.
     *
     * @return array An array of stylesheet files to include
     */
    public function get()
    {
        return $this->stylesheets;
    }

    /**
     * Returns HTML representation of the links to stylesheets.
     *
     * @return string The HTML representation of the stylesheets
     */
    public function render()
    {
        $html = '';
        foreach ($this->stylesheets as $path => $attributes) {
            $atts = '';
            foreach ($attributes as $key => $value) {
                $atts .= ' '.sprintf('%s="%s"', $key, htmlspecialchars($value, ENT_QUOTES, $this->charset));
            }

            $html .= sprintf('<link href="%s" rel="stylesheet" type="text/css"%s />', $path, $atts)."\n";
        }

        return $html;
    }

    /**
     * Outputs HTML representation of the links to stylesheets.
     *
     */
    public function output()
    {
        echo $this->render();
    }

    /**
     * Returns a string representation of this helper as HTML.
     *
     * @return string The HTML representation of the stylesheets
     */
    public function __toString()
    {
        return $this->render();
    }

    /**
     * Returns the canonical name of this helper.
     *
     * @return string The canonical name
     */
    public function getName()
    {
        return 'stylesheets';
    }
}
