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

// PHP_VERSION_ID is available as of PHP 5.2.7, if our
// version is lower than that, then emulate it
if(!defined('PHP_VERSION_ID'))
{
  $version = explode('.',PHP_VERSION);

  define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));
}

/**
 * LimeAutoloader is an autoloader for the lime test framework classes.
 *
 * Use the method register() to activate autoloading for all classes of this
 * component.
 *
 * <code>
 * include 'path/to/LimeAutoloader.php';
 * LimeAutoloader::register();
 * </code>
 *
 * Bundled with this component comes a backwards compatibility layer that
 * offers class and method signatures of lime 1.0 (lime_test, lime_harness etc.).
 * To activate this layer, call the method LimeAutoloader::enableLegacyMode()
 * anytime before using any of the old class names in your code.
 *
 * <code>
 * include 'path/to/LimeAutoloader.php';
 * LimeAutoloader::register();
 * LimeAutoloader::enableLegacyMode();
 * </code>
 *
 * @package    symfony
 * @subpackage lime
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeAutoloader.php 24189 2009-11-20 11:29:03Z bschussek $
 */
class LimeAutoloader
{
  static protected
    $isLegacyMode = false,
    $isRegistered = false;

  /**
   * Enables a backwards compatibility layer to allow use of old class names
   * such as lime_test, lime_output etc.
   */
  static public function enableLegacyMode()
  {
    self::$isLegacyMode = true;
  }

  /**
   * Registers LimeAutoloader as an SPL autoloader.
   */
  static public function register()
  {
    if (!self::$isRegistered)
    {
      ini_set('unserialize_callback_func', 'spl_autoload_call');
      spl_autoload_register(array(new self, 'autoload'));

      self::$isRegistered = true;
    }
  }

  /**
   * Handles autoloading of classes.
   *
   * @param  string  $class  A class name.
   *
   * @return boolean Returns true if the class has been loaded
   */
  public function autoload($class)
  {
    // backwards compatibility
    if (0 === strpos($class, 'lime_') && self::$isLegacyMode)
    {
      require_once dirname(__FILE__).'/lime.php';

      return true;
    }

    if (0 === strpos($class, 'Lime'))
    {
      $file = dirname(__FILE__).'/';

      if (0 === strpos($class, 'LimeExpectation'))
      {
        $file .= 'expectation/';
      }
      else if (0 === strpos($class, 'LimeLexer'))
      {
        $file .= 'lexer/';
      }
      else if (0 === strpos($class, 'LimeParser'))
      {
        $file .= 'parser/';
      }
      else if (0 === strpos($class, 'LimeOutput'))
      {
        $file .= 'output/';
      }
      else if (0 === strpos($class, 'LimeMockInvocationMatcher'))
      {
        $file .= 'mock/matcher/';
      }
      else if (0 === strpos($class, 'LimeMock'))
      {
        $file .= 'mock/';
      }
      else if (0 === strpos($class, 'LimeTester'))
      {
        $file .= 'tester/';
      }
      else if (0 === strpos($class, 'LimeShell'))
      {
        $file .= 'shell/';
      }
      else if (0 === strpos($class, 'LimeConstraint'))
      {
        $file .= 'constraint/';
      }

      $file .= $class.'.php';

      if (file_exists($file))
      {
        require_once $file;

        return true;
      }
    }

    return false;
  }
}

/**
 * Prints the given value to the error stream in a nicely formatted way.
 *
 * @param mixed $value
 */
function lime_debug($value)
{
  $result = "";

  if (is_object($value) || is_array($value))
  {
    $result = is_object($value) ? sprintf("object(%s) (\n", get_class($value)) : "array (";

    if (is_object($value))
    {
      $value = LimeTesterObject::toArray($value);
    }

    foreach ($value as $key => $val)
    {
      if (is_object($val) || is_array($val))
      {
        $output = is_object($val) ? sprintf("object(%s) (", get_class($val)) : "array (";

        if (is_object($val))
        {
          $val = LimeTesterObject::toArray($val);
        }

        if (count($val) > 0)
        {
          $output .= "\n    ...\n  ";
        }

        $output .= ")";
      }
      else
      {
        if (is_string($val) && strlen($val) > 60)
        {
          $val = substr($val, 0, 57).'...';
        }

        $output = lime_colorize($val);
      }

      $result .= sprintf("  %s => %s,\n", var_export($key, true), $output);
    }

    $result .= ")";
  }
  else
  {
    $result = lime_colorize($value);
  }

  fwrite(STDERR, $result."\n");
}

/**
 * Returns a colorized export of the given value depending on its type.
 *
 * @param  mixed $value
 * @return string
 */
function lime_colorize($value)
{
  static $colorizer = null;

  if (is_null($colorizer) && LimeColorizer::isSupported())
  {
    $colorizer = new LimeColorizer();
    $colorizer->setStyle('string', array('fg' => 'cyan'));
    $colorizer->setStyle('integer', array('fg' => 'green'));
    $colorizer->setStyle('double', array('fg' => 'green'));
    $colorizer->setStyle('boolean', array('fg' => 'red'));
  }

  $type = gettype($value);
  $value = var_export($value, true);

  if (!is_null($colorizer) && in_array($type, array('string', 'integer', 'double', 'boolean')))
  {
    $value = $colorizer->colorize($value, $type);
  }

  return $value;
}