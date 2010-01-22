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

require_once dirname(__FILE__).'/LimeAutoloader.php';

LimeAutoloader::enableLegacyMode();
LimeAutoloader::register();

class lime_test extends LimeTest
{
  public function __construct($plan = null, $options = array())
  {
    // for BC
    if (!is_array($options))
    {
      $options = array(); // drop the old output because it is not compatible with LimeTest
    }

    parent::__construct($plan, $options);
  }

  static public function to_array()
  {
    return self::toArray();
  }

  static public function to_xml($results = null)
  {
    return self::toXml($results);
  }

  /**
   * Compares two arguments with an operator
   *
   * @param mixed  $exp1    left value
   * @param string $op      operator
   * @param mixed  $exp2    right value
   * @param string $message display output message when the test passes
   *
   * @return boolean
   */
  public function cmp_ok($exp1, $op, $exp2, $message = '')
  {
    switch ($op)
    {
      case '===':
        return $this->same($exp1, $exp2, $message);
      case '!==':
        return $this->isntSame($exp1, $exp2, $message);
      case '==':
        return $this->is($exp1, $exp2, $message);
      case '!=':
        return $this->isnt($exp1, $exp2, $message);
      case '<':
        return $this->lessThan($exp1, $exp2, $message);
      case '<=':
        return $this->lessThanEqual($exp1, $exp2, $message);
      case '>':
        return $this->greaterThan($exp1, $exp2, $message);
      case '>=':
        return $this->greaterThanEqual($exp1, $exp2, $message);
      default:
        throw new InvalidArgumentException(sprintf('Unknown operation "%s"', $op));
    }
  }

  /**
   * Checks the availability of a method for an object or a class
   *
   * @param mixed        $object  an object instance or a class name
   * @param string|array $methods one or more method names
   * @param string       $message display output message when the test passes
   *
   * @return boolean
   */
  public function can_ok($object, $methods, $message = '')
  {
    $result = true;
    $failedMessages = array();
    foreach ((array) $methods as $method)
    {
      if (!method_exists($object, $method))
      {
        $failedMessages[] = sprintf("method '%s' does not exist", $method);
        $result = false;
      }
    }

    return $this->test_ok($result, $message, implode("\n", $failedMessages));
  }

  /**
   * Checks the type of an argument
   *
   * @param mixed  $var     variable instance
   * @param string $class   class or type name
   * @param string $message display output message when the test passes
   *
   * @return boolean
   */
  public function isa_ok($var, $class, $message = '')
  {
    $type = is_object($var) ? get_class($var) : gettype($var);
    $error = sprintf("variable isn't a '%s' it's a '%s'", $class, $type);

    return $this->test_ok($type == $class, $message, $error);
  }

  public function is_deeply($exp1, $exp2, $message = '')
  {
    return $this->is($exp1, $exp2, $message);
  }

  public function include_ok($file, $message = '')
  {
    return $this->includeOk($file, $message);
  }

  public function error($message)
  {
    list($file, $line) = LimeTrace::findCaller('lime_test');

    $this->output->error(new LimeError($message, $file, $line));
  }

  /**
   * @deprecated Use comment() instead
   * @param $message
   * @return unknown_type
   */
  public function info($message)
  {
    if ($this->output instanceof LimeOutputTap)
    {
      $this->output->info($message);
    }
  }

  private function test_ok($condition, $message, $error = null)
  {
    list ($file, $line) = LimeTrace::findCaller('LimeTest');

    if ($result = (boolean) $condition)
    {
      $this->output->pass($message, $file, $line);
    }
    else
    {
      $this->output->fail($message, $file, $line, $error);
    }

    return $result;
  }
}

class lime_output
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

class lime_output_color extends lime_output
{
}

class lime_colorizer extends LimeColorizer
{
  protected static
    $instances    = array(),
    $staticStyles = array();

  public function __construct()
  {
    self::$instances[] = $this;
    $this->styles = self::$staticStyles;
  }

  public static function style($name, $options = array())
  {
    foreach (self::$instances as $instance)
    {
      $instance->setStyle($name, $options);
    }
    self::$staticStyles[$name] = $options;
  }
}

class lime_harness extends LimeTestSuite
{
  public function __construct($options = array())
  {
    // for BC
    if (!is_array($options))
    {
      $options = array(); // drop the old output because it is not compatible with LimeTest
    }
    else if (array_key_exists('php_cli', $options))
    {
      $options['executable'] = $options['php_cli'];
      unset($options['php_cli']);
    }

    parent::__construct($options);
  }

  public function to_array()
  {
    return $this->toArray();
  }

  public function to_xml()
  {
    return $this->toXml();
  }

  public function get_failed_files()
  {
    return $this->output->getFailedFiles();
  }
}

class lime_coverage extends LimeCoverage
{
  public static function get_php_lines($content)
  {
    return self::getPhpLines($content);
  }

  public function format_range($lines)
  {
    return $this->formatRange($lines);
  }
}

class lime_registration extends LimeRegistration
{
  public function register_glob($glob)
  {
    return $this->registerGlob($glob);
  }

  public function register_dir($directory)
  {
    return $this->registerDir($directory);
  }
}
