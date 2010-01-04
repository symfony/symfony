<?php

/*
 * This file is part of the Lime framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * (c) Bernhard Schussek <bernhard.schussek@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Colorizes text strings for output in a console.
 *
 * You can colorize text by calling the method colorize() with a given text
 * string:
 *
 * <code>
 * $colorizer = new LimeColorizer();
 * $text = $colorizer->colorize('Hello World', array(
 *   'bold' => true,
 *   'fg' => 'white',
 *   'bg' => 'blue',
 * ));
 * </code>
 *
 * You can also predefine styles using the static method setStyle().
 *
 * Use the static method isSupported() to find out whether colorization is
 * supported by the current OS and console.
 *
 * @package    lime
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeColorizer.php 23701 2009-11-08 21:23:40Z bschussek $
 */
class LimeColorizer
{
  static protected
    $fontStyles = array(
      'bold'        => 1,
      'underscore'  => 4,
      'blink'       => 5,
      'reverse'     => 7,
      'conceal'     => 8,
    ),
    $foregroundColors = array(
      'black'   => 30,
      'red'     => 31,
      'green'   => 32,
      'yellow'  => 33,
      'blue'    => 34,
      'magenta' => 35,
      'cyan'    => 36,
      'white'   => 37,
    ),
    $backgroundColors = array(
      'black'   => 40,
      'red'     => 41,
      'green'   => 42,
      'yellow'  => 43,
      'blue'    => 44,
      'magenta' => 45,
      'cyan'    => 46,
      'white'   => 47,
    );

  protected
    $styles     = array();

  /**
   * Returns whether colorization is supported by the current OS and console.
   *
   * This method returns true if either the current operating system is
   * Windows or the console is not a tty console.
   *
   * @return boolean
   */
  public static function isSupported()
  {
    return DIRECTORY_SEPARATOR != '\\' && function_exists('posix_isatty') && @posix_isatty(STDOUT);
  }

  /**
   * Registers a color style under a given name.
   *
   * The options array may contain the following entries:
   *
   * <table>
   *   <tr><th>Option</th>    <th>Value Type</th> <th>Description</th></tr>
   *   <tr><td>bold</td>      <td>boolean</td>    <td><tt>true</tt> for bold text</td></tr>
   *   <tr><td>underscore</td><td>boolean</td>    <td><tt>true</tt> for underscored text</td></tr>
   *   <tr><td>blink</td>     <td>boolean</td>    <td><tt>true</tt> for blinking text</td></tr>
   *   <tr><td>reverse</td>   <td>boolean</td>    <td><tt>true</tt> for text with inverted foreground/background color</td></tr>
   *   <tr><td>conceal</td>   <td>boolean</td>    <td><tt>true</tt> for invisible text</td></tr>
   *   <tr><td>fg</td>        <td>color</td>      <td>The color of the text</td></tr>
   *   <tr><td>bg</td>        <td>color</td>      <td>The color of the background</td></tr>
   * </table>
   *
   * The following color values are supported:
   *
   *   * black
   *   * red
   *   * green
   *   * yellow
   *   * blue
   *   * magenta
   *   * cyan
   *   * white
   *
   * Example:
   *
   * <code>
   * $colorizer = new LimeColorizer();
   * $colorizer->setStyle('myStyle', array(
   *   'bold' => true,
   *   'fg' => 'white',
   *   'bg' => 'blue',
   * ));
   * </code>
   *
   * @param  string $name
   * @param  array  $options
   */
  public function setStyle($name, array $options = array())
  {
    $this->styles[$name] = $options;
  }

  /**
   * Colorizes a given text.
   *
   * The second parameter can either be the name of a style predefined with
   * setStyle() or an array of style options. For more information about the
   * possible style options, see the description of setStyle().
   *
   * The returned string contains special codes that are interpreted by the
   * shell to format the output.
   *
   * Example (with options):
   *
   * <code>
   * $colorizer = new LimeColorizer();
   * $text = $colorizer->colorize('Hello World', array(
   *   'bold' => true,
   *   'fg' => 'white',
   *   'bg' => 'blue',
   * ));
   * </code>
   *
   * Example (with style name):
   *
   * <code>
   * $colorizer = new LimeColorizer();
   * $colorizer->setStyle('myStyle', array(
   *   'bold' => true,
   *   'fg' => 'white',
   *   'bg' => 'blue',
   * ));
   * $text = $colorizer->colorize('Hello World', 'myStyle');
   * </code>
   *
   * @param  string       $text        The text to colorize
   * @param  string|array $parameters  The style name or style options
   *
   * @return string                    The colorized text
   */
  public function colorize($text = '', $parameters = array())
  {
    if (!is_array($parameters) && isset(self::$this->styles[$parameters]))
    {
      $parameters = $this->styles[$parameters];
    }

    $codes = array();
    if (isset($parameters['fg']))
    {
      $codes[] = self::$foregroundColors[$parameters['fg']];
    }
    if (isset($parameters['bg']))
    {
      $codes[] = self::$backgroundColors[$parameters['bg']];
    }

    foreach (self::$fontStyles as $fontStyle => $code)
    {
      if (isset($parameters[$fontStyle]) && $parameters[$fontStyle])
      {
        $codes[] = $code;
      }
    }

    return "\033[".implode(';', $codes).'m'.$text."\033[0m";
  }
}