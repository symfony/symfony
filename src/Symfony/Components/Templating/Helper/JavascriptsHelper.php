<?php

namespace Symfony\Components\Templating\Helper;

/*
 * This file is part of the symfony package.
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
 *   $this->javascripts->add('foo.css', array('media' => 'print'));
 *   echo $this->javascripts;
 * </code>
 *
 * @package    symfony
 * @subpackage templating
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
class JavascriptsHelper extends Helper
{
  protected
    $javascripts = array();

  /**
   * Adds a JavaScript file.
   *
   * @param string $javascript A JavaScript file path
   * @param array  $attributes An array of attributes
   */
  public function add($javascript, $attributes = array())
  {
    $this->javascripts[$this->helperSet->get('assets')->getUrl($javascript)] = $attributes;
  }

  /**
   * Returns all JavaScript files.
   *
   * @param array An array of JavaScript files to include
   */
  public function get()
  {
    return $this->javascripts;
  }

  /**
   * Returns a string representation of this helper as HTML.
   *
   * @return string The HTML representation of the JavaScripts
   */
  public function __toString()
  {
    $html = array();
    foreach ($this->javascripts as $path => $attributes)
    {
      $atts = array();
      foreach ($attributes as $key => $value)
      {
        $atts[] = sprintf('%s="%s"', $key, $this->helperSet->getEngine()->escape($value));
      }

      $html[] = sprintf('<script type="text/javascript" src="%s" %s></script>', $path, implode(' ', $atts));
    }

    return implode("\n", $html);
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
