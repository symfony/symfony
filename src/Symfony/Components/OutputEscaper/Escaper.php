<?php

namespace Symfony\Components\OutputEscaper;

require_once __DIR__.'/escaping_helpers.php';

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Abstract class that provides an interface for escaping of output.
 *
 * @package    symfony
 * @subpackage output_escaper
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Mike Squire <mike@somosis.co.uk>
 * @version    SVN: $Id: Escaper.class.php 21908 2009-09-11 12:06:21Z fabien $
 */
abstract class Escaper
{
  /**
   * The value that is to be escaped.
   *
   * @var mixed
   */
  protected $value;

  /**
   * The escaping method that is going to be applied to the value and its
   * children. This is actually a PHP callable.
   *
   * @var string
   */
  protected $escapingMethod;

  static protected $charset = 'UTF-8';

  static protected $safeClasses = array();

  static protected $strategies = array();

  /**
   * Constructor stores the escaping method and value.
   *
   * Since Escaper is an abstract class, instances cannot be created
   * directly but the constructor will be inherited by sub-classes.
   *
   * @param string $escapingMethod  Escaping method
   * @param string $value           Escaping value
   */
  public function __construct($escapingMethod, $value)
  {
    $this->value          = $value;
    $this->escapingMethod = $escapingMethod;
  }

  /**
   * Decorates a PHP variable with something that will escape any data obtained
   * from it.
   *
   * The following cases are dealt with:
   *
   *    - The value is null or false: null or false is returned.
   *    - The value is scalar: the result of applying the escaping method is
   *      returned.
   *    - The value is an array or an object that implements the ArrayAccess
   *      interface: the array is decorated such that accesses to elements yield
   *      an escaped value.
   *    - The value implements the Traversable interface (either an Iterator, an
   *      IteratorAggregate or an internal PHP class that implements
   *      Traversable): decorated much like the array.
   *    - The value is another type of object: decorated such that the result of
   *      method calls is escaped.
   *
   * The escaping method is actually a PHP callable. This class hosts a set
   * of standard escaping methods.
   *
   * @param  string $escapingMethod  The escaping method (a PHP callable) to apply to the value
   * @param  mixed  $value           The value to escape
   *
   * @return mixed Escaping value
   *
   * @throws \InvalidArgumentException If the escaping fails
   */
  static public function escape($escapingMethod, $value)
  {
    if (null === $value)
    {
      return $value;
    }

    // Scalars are anything other than arrays, objects and resources.
    if (is_scalar($value))
    {
      return call_user_func($escapingMethod, $value);
    }

    if (is_array($value))
    {
      return new ArrayDecorator($escapingMethod, $value);
    }

    if (is_object($value))
    {
      if ($value instanceof Escaper)
      {
        // avoid double decoration
        $copy = clone $value;

        $copy->escapingMethod = $escapingMethod;

        return $copy;
      }
      else if (self::isClassMarkedAsSafe(get_class($value)))
      {
        // the class or one of its children is marked as safe
        // return the unescaped object
        return $value;
      }
      else if ($value instanceof Safe)
      {
        // do not escape objects marked as safe
        // return the original object
        return $value->getValue();
      }
      else if ($value instanceof \Traversable)
      {
        return new IteratorDecorator($escapingMethod, $value);
      }
      else
      {
        return new ObjectDecorator($escapingMethod, $value);
      }
    }

    // it must be a resource; cannot escape that.
    throw new \InvalidArgumentException(sprintf('Unable to escape value "%s".', var_export($value, true)));
  }

  /**
   * Unescapes a value that has been escaped previously with the escape() method.
   *
   * @param  mixed $value The value to unescape
   *
   * @return mixed Unescaped value
   *
   * @throws \InvalidArgumentException If the escaping fails
   */
  static public function unescape($value)
  {
    if (null === $value || is_bool($value))
    {
      return $value;
    }

    if (is_scalar($value))
    {
      return html_entity_decode($value, ENT_QUOTES, self::$charset);
    }
    elseif (is_array($value))
    {
      foreach ($value as $name => $v)
      {
        $value[$name] = self::unescape($v);
      }

      return $value;
    }
    elseif (is_object($value))
    {
      return $value instanceof Escaper ? $value->getRawValue() : $value;
    }

    return $value;
  }

  /**
   * Returns true if the class if marked as safe.
   *
   * @param  string  $class  A class name
   *
   * @return bool true if the class if safe, false otherwise
   */
  static public function isClassMarkedAsSafe($class)
  {
    if (in_array($class, self::$safeClasses))
    {
      return true;
    }

    foreach (self::$safeClasses as $safeClass)
    {
      if (is_subclass_of($class, $safeClass))
      {
        return true;
      }
    }

    return false;
  }

  /**
   * Marks an array of classes (and all its children) as being safe for output.
   *
   * @param array $classes  An array of class names
   */
  static public function markClassesAsSafe(array $classes)
  {
    self::$safeClasses = array_unique(array_merge(self::$safeClasses, $classes));
  }

  /**
   * Marks a class (and all its children) as being safe for output.
   *
   * @param string $class  A class name
   */
  static public function markClassAsSafe($class)
  {
    self::markClassesAsSafe(array($class));
  }

  /**
   * Returns the raw value associated with this instance.
   *
   * Concrete instances of Escaper classes decorate a value which is
   * stored by the constructor. This returns that original, unescaped, value.
   *
   * @return mixed The original value used to construct the decorator
   */
  public function getRawValue()
  {
    return $this->value;
  }

  /**
   * Gets a value from the escaper.
   *
   * @param  string $var  Value to get
   *
   * @return mixed Value
   */
  public function __get($var)
  {
    return $this->escape($this->escapingMethod, $this->value->$var);
  }

  /**
   * Sets the current charset.
   *
   * @param string $charset The current charset
   */
  static public function setCharset($charset)
  {
    self::$charset = $charset;
  }

  /**
   * Gets the current charset.
   *
   * @return string The current charset
   */
  static public function getCharset()
  {
    return self::$charset;
  }
}
