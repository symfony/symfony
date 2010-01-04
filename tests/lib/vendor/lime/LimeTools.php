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
 * Provides static utility methods.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeTools.php 23864 2009-11-13 18:06:20Z bschussek $
 */
abstract class LimeTools
{
  /**
   * Indents every line of the given string for the given number of spaces
   * (except for the first line, which is not indented).
   *
   * @param  string  $text            The input string
   * @param  integer $numberOfSpaces  The number of spaces for indenting the
   *                                  input string
   * @return string                   The indented string
   */
  public static function indent($text, $numberOfSpaces)
  {
    $indentation = str_repeat(' ', $numberOfSpaces);
    $lines = explode("\n", $text);

    for ($i = 0, $c = count($lines); $i < $c; ++$i)
    {
      $lines[$i] = $indentation.$lines[$i];
    }

    return implode("\n", $lines);
  }
}