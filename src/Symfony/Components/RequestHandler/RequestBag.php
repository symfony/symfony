<?php

namespace Symfony\Components\RequestHandler;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * RequestBag is a container for key/value pairs.
 *
 * @package    Symfony
 * @subpackage Components_RequestHandler
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class RequestBag
{
  protected $input;

  public function __construct($input)
  {
    $this->input = $input;
  }

  /**
   * Returns the input.
   *
   * @return array An array of input
   */
  public function all()
  {
    return $this->input;
  }

  public function replace($input)
  {
    $this->input = $input;
  }

  /**
   * Returns a parameter by name.
   *
   * @param string $key     The key
   * @param mixed  $default The default value
   */
  public function get($key, $default = null)
  {
    return array_key_exists($key, $this->input) ? $this->input[$key] : $default;
  }

  /**
   * Sets a parameter by name.
   *
   * @param string $key   The key
   * @param mixed  $value The value
   */
  public function set($key, $value)
  {
    $this->input[$key] = $value;
  }

  public function has($key)
  {
    return array_key_exists($key, $this->input);
  }

  /**
   * Returns the alphabetic characters of the parameter value.
   *
   * @param string $key     The parameter key
   * @param mixed  $default The default value
   *
   * @return string The filtered value
   */
  public function getAlpha($key, $default = '')
  {
    return preg_replace('/[^[:alpha:]]/', '', $this->get($key, $default));
  }

  /**
   * Returns the alphabetic characters and digits of the parameter value.
   *
   * @param string $key     The parameter key
   * @param mixed  $default The default value
   *
   * @return string The filtered value
   */
  public function getAlnum($key, $default = '')
  {
    return preg_replace('/[^[:alnum:]]/', '', $this->get($key, $default));
  }

  /**
   * Returns the digits of the parameter value.
   *
   * @param string $key     The parameter key
   * @param mixed  $default The default value
   *
   * @return string The filtered value
   */
  public function getDigits($key, $default = '')
  {
    return preg_replace('/[^[:digit:]]/', '', $this->get($key, $default));
  }

  /**
   * Returns the parameter value converted to integer.
   *
   * @param string $key     The parameter key
   * @param mixed  $default The default value
   *
   * @return string The filtered value
   */
  public function getInt($key, $default = 0)
  {
    return (int) $this->get($key, $default);
  }
}
