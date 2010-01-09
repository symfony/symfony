<?php

namespace Symfony\Components\Console\Output;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * The Formatter class provides helpers to format messages.
 *
 * @package    symfony
 * @subpackage cli
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Formatter
{
  /**
   * Formats a message within a section.
   *
   * @param string  $section The section name
   * @param string  $message The message
   * @param string  $style   The style to apply to the section
   */
  static public function formatSection($section, $message, $style = 'info')
  {
    return sprintf("<%s>[%s]</%s> %s", $style, $section, $style, $message);
  }

  /**
   * Formats a message as a block of text.
   *
   * @param string|array $messages The message to write in the block
   * @param string       $style    The style to apply to the whole block
   * @param Boolean      $large    Whether to return a large block
   *
   * @return string The formatter message
   */
  static public function formatBlock($messages, $style, $large = false)
  {
    if (!is_array($messages))
    {
      $messages = array($messages);
    }

    $len = 0;
    $lines = array();
    foreach ($messages as $message)
    {
      $lines[] = sprintf($large ? '  %s  ' : ' %s ', $message);
      $len = max(static::strlen($message) + ($large ? 4 : 2), $len);
    }

    $messages = $large ? array(str_repeat(' ', $len)) : array();
    foreach ($lines as $line)
    {
      $messages[] = $line.str_repeat(' ', $len - static::strlen($line));
    }
    if ($large)
    {
      $messages[] = str_repeat(' ', $len);
    }

    foreach ($messages as &$message)
    {
      $message = sprintf('<%s>%s</%s>', $style, $message, $style);
    }

    return implode("\n", $messages);
  }

  static protected function strlen($string)
  {
    return function_exists('mb_strlen') ? mb_strlen($string) : strlen($string);
  }
}
