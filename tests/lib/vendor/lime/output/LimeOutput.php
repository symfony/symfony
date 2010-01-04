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
 * Prints text on the console in different formats.
 *
 * You can use the various methods in this class to print nicely formatted
 * text message in the console. If the console does not support text formatting,
 * text formatting is suppressed, unless you pass the argument $forceColors=TRUE
 * in the constructor.
 *
 * @package    symfony
 * @subpackage lime
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id$
 */
class LimeOutput
{
  const
    ERROR     = 'ERROR',
    INFO      = 'INFO',
    PARAMETER = 'PARAMETER',
    COMMENT   = 'COMMENT',
    GREEN_BAR = 'GREEN_BAR',
    RED_BAR   = 'RED_BAR',
    INFO_BAR  = 'INFO_BAR';

  protected static
    $styles = array(self::ERROR, self::INFO, self::PARAMETER, self::COMMENT, self::GREEN_BAR, self::RED_BAR, self::INFO_BAR);

  protected
    $colorizer = null;

  /**
   * Constructor.
   *
   * @param  boolean $forceColors  If set to TRUE, colorization will be enforced
   *                               whether or not the current console supports it
   */
  public function __construct($forceColors = false)
  {
    if (LimeColorizer::isSupported() || $forceColors)
    {
      $colorizer = new LimeColorizer();
      $colorizer->setStyle(self::ERROR, array('bg' => 'red', 'fg' => 'white', 'bold' => true));
      $colorizer->setStyle(self::INFO, array('fg' => 'green', 'bold' => true));
      $colorizer->setStyle(self::PARAMETER, array('fg' => 'cyan'));
      $colorizer->setStyle(self::COMMENT, array('fg' => 'yellow'));
      $colorizer->setStyle(self::GREEN_BAR, array('fg' => 'white', 'bg' => 'green', 'bold' => true));
      $colorizer->setStyle(self::RED_BAR, array('fg' => 'white', 'bg' => 'red', 'bold' => true));
      $colorizer->setStyle(self::INFO_BAR, array('fg' => 'cyan', 'bold' => true));

      $this->colorizer = $colorizer;
    }
  }

  /**
   * Colorizes the given text with the given style.
   *
   * @param  string $text   Some text
   * @param  string $style  One of the predefined style constants
   * @return string         The formatted text
   */
  protected function colorize($text, $style)
  {
    if (!in_array($style, self::$styles))
    {
      throw new InvalidArgumentException(sprintf('The style "%s" does not exist', $style));
    }

    return is_null($this->colorizer) ? $text : $this->colorizer->colorize($text, $style);
  }

  /**
   * ?
   */
  public function diag()
  {
    $messages = func_get_args();
    foreach ($messages as $message)
    {
      echo $this->colorize('# '.join("\n# ", (array) $message), self::COMMENT)."\n";
    }
  }

  /**
   * Prints a comment.
   *
   * @param  string $message
   */
  public function comment($message)
  {
    echo $this->colorize(sprintf('# %s', $message), self::COMMENT)."\n";
  }

  /**
   * Prints an informational message.
   *
   * @param  string $message
   */
  public function info($message)
  {
    echo $this->colorize(sprintf('> %s', $message), self::INFO_BAR)."\n";
  }

  /**
   * Prints an error.
   *
   * @param string $message
   */
  public function error($message)
  {
    echo $this->colorize(sprintf(' %s ', $message), self::RED_BAR)."\n";
  }

  /**
   * Prints and automatically colorizes a line.
   *
   * You can wrap the whole line into a specific predefined style by passing
   * the style constant in the second parameter.
   *
   * @param string  $message   The message to colorize
   * @param string  $style     The desired style constant
   * @param boolean $colorize  Whether to automatically colorize parts of the
   *                           line
   */
  public function echoln($message, $style = null, $colorize = true)
  {
    if ($colorize)
    {
      $message = preg_replace('/(?:^|\.)((?:not ok|dubious) *\d*)\b/e', '$this->colorize(\'$1\', self::ERROR)', $message);
      $message = preg_replace('/(?:^|\.)(ok *\d*)\b/e', '$this->colorize(\'$1\', self::INFO)', $message);
      $message = preg_replace('/"(.+?)"/e', '$this->colorize(\'$1\', self::PARAMETER)', $message);
      $message = preg_replace('/(\->|\:\:)?([a-zA-Z0-9_]+?)\(\)/e', '$this->colorize(\'$1$2()\', self::PARAMETER)', $message);
    }

    echo ($style ? $this->colorize($message, $style) : $message)."\n";
  }

  /**
   * Prints a message in a green box.
   *
   * @param string $message
   */
  public function greenBar($message)
  {
    echo $this->colorize($message.str_repeat(' ', 71 - min(71, strlen($message))), self::GREEN_BAR)."\n";
  }

  /**
   * Prints a message a in a red box.
   *
   * @param string $message
   */
  public function redBar($message)
  {
    echo $this->colorize($message.str_repeat(' ', 71 - min(71, strlen($message))), self::RED_BAR)."\n";
  }
}