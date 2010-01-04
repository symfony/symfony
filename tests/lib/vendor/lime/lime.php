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

class lime_output extends LimeOutput
{
  public function green_bar($message)
  {
    return $this->greenBar($message);
  }

  public function red_bar($message)
  {
    return $this->redBar($message);
  }
}

class lime_output_color extends LimeOutput
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
