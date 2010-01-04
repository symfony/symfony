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

class LimePrinter
{
  const
    OK = 0,
    NOT_OK = 1,
    COMMENT = 2,
    SKIP = 3,
    WARNING = 4,
    ERROR = 5,
    HAPPY = 6,
    STRING = 7,
    NUMBER = 8,
    BOOLEAN = 9,
    INFO = 10,
    TRACE = 11,
    TODO = 12;

  protected
    $colorizer = null;

  public function __construct(LimeColorizer $colorizer = null)
  {
    if (!is_null($colorizer))
    {
      $colorizer->setStyle(self::OK, array('fg' => 'green', 'bold' => true));
      $colorizer->setStyle(self::NOT_OK, array('bg' => 'red', 'fg' => 'white', 'bold' => true));
      $colorizer->setStyle(self::COMMENT, array('fg' => 'yellow'));
      $colorizer->setStyle(self::SKIP, array('fg' => 'yellow', 'bold' => true));
      $colorizer->setStyle(self::TODO, array('fg' => 'yellow', 'bold' => true));
      $colorizer->setStyle(self::WARNING, array('fg' => 'white', 'bg' => 'yellow', 'bold' => true));
      $colorizer->setStyle(self::ERROR, array('bg' => 'red', 'fg' => 'white', 'bold' => true));
      $colorizer->setStyle(self::HAPPY, array('fg' => 'white', 'bg' => 'green', 'bold' => true));
      $colorizer->setStyle(self::STRING, array('fg' => 'cyan'));
      $colorizer->setStyle(self::NUMBER, array());
      $colorizer->setStyle(self::BOOLEAN, array('fg' => 'cyan'));
      $colorizer->setStyle(self::INFO, array('fg' => 'cyan', 'bold' => true));
      $colorizer->setStyle(self::TRACE, array('fg' => 'green', 'bold' => true));
    }

    $this->colorizer = $colorizer;
  }

  public function printText($text, $style = null)
  {
    print $this->colorize($text, $style);
  }

  public function printLine($text, $style = null)
  {
    print $this->colorize($text, $style)."\n";
  }

  public function printBox($text, $style = null)
  {
    print $this->colorize(str_pad($text, 80, ' '), $style)."\n";
  }

  public function printLargeBox($text, $style = null)
  {
    $space = $this->colorize(str_repeat(' ', 80), $style)."\n";
    $text = trim($text);
    $text = wordwrap($text, 75, "\n");

    print "\n".$space;
    foreach (explode("\n", $text) as $line)
    {
      print $this->colorize(str_pad('  '.$line, 80, ' '), $style)."\n";
    }
    print $space."\n";
  }

  protected function colorize($text, $style)
  {
    if (is_null($this->colorizer))
    {
      return $text;
    }
    else
    {
      if (is_null($style))
      {
        return preg_replace_callback('/("[^"]*"|(?<!\w)\d+(\.\d+)?(?!\w)|true|false'/*|(->|::)?\w+\([^\)]*\)*/.')/', array($this, 'autoColorize'), $text);
      }
      else
      {
        return $this->colorizer->colorize($text, $style);
      }
    }
  }

  public function autoColorize($text)
  {
    $text = $text[0];

    if (is_null($this->colorizer))
    {
      return $text;
    }
    else
    {
      if ($text{0} == '"')
      {
        return $this->colorizer->colorize($text, self::STRING);
      }
      else if (in_array(strtolower($text), array('true', 'false')))
      {
        return $this->colorizer->colorize($text, self::BOOLEAN);
      }
      else
      {
        return $this->colorizer->colorize($text, self::NUMBER);
      }
    }
  }
}