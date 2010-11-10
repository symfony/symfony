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
 * JavascriptsHelper is a helper that manages JavaScripts.
 *
 * Usage:
 *
 * <code>
 *   $view['javascripts']->add('foo.js');
 *   echo $view['javascripts'];
 * </code>
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class JavascriptsHelper extends Helper
{
    protected $javascripts = array();
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
     * Adds a JavaScript file.
     *
     * @param string $javascript A JavaScript file path
     * @param array  $attributes An array of attributes
     */
    public function add($javascript, $attributes = array())
    {
        $this->javascripts[$this->assetHelper->getUrl($javascript)] = $attributes;
    }

    /**
     * Returns all JavaScript files.
     *
     * @return array An array of JavaScript files to include
     */
    public function get()
    {
        return $this->javascripts;
    }

    /**
     * Returns HTML representation of the links to JavaScripts.
     *
     * @return string The HTML representation of the JavaScripts
     */
    public function render()
    {
        $html = '';
        foreach ($this->javascripts as $path => $attributes) {
            $atts = '';
            foreach ($attributes as $key => $value) {
                $atts .= ' '.sprintf('%s="%s"', $key, htmlspecialchars($value, ENT_QUOTES, $this->charset));
            }

            $html .= sprintf('<script type="text/javascript" src="%s"%s></script>', $path, $atts)."\n";
        }

        return $html;
    }

    /**
     * Outputs HTML representation of the links to JavaScripts.
     *
     */
    public function output()
    {
        echo $this->render();
    }

    /**
     * Returns a string representation of this helper as HTML.
     *
     * @return string The HTML representation of the JavaScripts
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
        return 'javascripts';
    }
}
