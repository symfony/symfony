<?php

namespace Symfony\Components\Console\Input;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Represents a command line option.
 *
 * @package    symfony
 * @subpackage console
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class InputOption
{
  const PARAMETER_NONE     = 1;
  const PARAMETER_REQUIRED = 2;
  const PARAMETER_OPTIONAL = 4;
  const PARAMETER_IS_ARRAY = 8;

  protected $name;
  protected $shortcut;
  protected $mode;
  protected $default;
  protected $description;

  /**
   * Constructor.
   *
   * @param string  $name        The option name
   * @param string  $shortcut    The shortcut (can be null)
   * @param integer $mode        The option mode: self::PARAMETER_REQUIRED, self::PARAMETER_NONE or self::PARAMETER_OPTIONAL
   * @param string  $description A description text
   * @param mixed   $default     The default value (must be null for self::PARAMETER_REQUIRED or self::PARAMETER_NONE)
   */
  public function __construct($name, $shortcut = null, $mode = null, $description = '', $default = null)
  {
    if ('--' === substr($name, 0, 2))
    {
      $name = substr($name, 2);
    }

    if (empty($shortcut))
    {
      $shortcut = null;
    }

    if (null !== $shortcut)
    {
      if ('-' === $shortcut[0])
      {
        $shortcut = substr($shortcut, 1);
      }
    }

    if (null === $mode)
    {
      $mode = self::PARAMETER_NONE;
    }
    else if (!is_int($mode) || $mode > 15)
    {
      throw new \InvalidArgumentException(sprintf('Option mode "%s" is not valid.', $mode));
    }

    $this->name        = $name;
    $this->shortcut    = $shortcut;
    $this->mode        = $mode;
    $this->description = $description;

    if ($this->isArray() && !$this->acceptParameter())
    {
      throw new \InvalidArgumentException('Impossible to have an option mode PARAMETER_IS_ARRAY if the option does not accept a parameter.');
    }

    $this->setDefault($default);
  }

  /**
   * Returns the shortcut.
   *
   * @return string The shortcut
   */
  public function getShortcut()
  {
    return $this->shortcut;
  }

  /**
   * Returns the name.
   *
   * @return string The name
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * Returns true if the option accept a parameter.
   *
   * @return Boolean true if parameter mode is not self::PARAMETER_NONE, false otherwise
   */
  public function acceptParameter()
  {
    return $this->isParameterRequired() || $this->isParameterOptional();
  }

  /**
   * Returns true if the option requires a parameter.
   *
   * @return Boolean true if parameter mode is self::PARAMETER_REQUIRED, false otherwise
   */
  public function isParameterRequired()
  {
    return self::PARAMETER_REQUIRED === (self::PARAMETER_REQUIRED & $this->mode);
  }

  /**
   * Returns true if the option takes an optional parameter.
   *
   * @return Boolean true if parameter mode is self::PARAMETER_OPTIONAL, false otherwise
   */
  public function isParameterOptional()
  {
    return self::PARAMETER_OPTIONAL === (self::PARAMETER_OPTIONAL & $this->mode);
  }

  /**
   * Returns true if the option can take multiple values.
   *
   * @return Boolean true if mode is self::PARAMETER_IS_ARRAY, false otherwise
   */
  public function isArray()
  {
    return self::PARAMETER_IS_ARRAY === (self::PARAMETER_IS_ARRAY & $this->mode);
  }

  /**
   * Sets the default value.
   *
   * @param mixed $default The default value
   */
  public function setDefault($default = null)
  {
    if (self::PARAMETER_NONE === (self::PARAMETER_NONE & $this->mode) && null !== $default)
    {
      throw new \LogicException('Cannot set a default value when using Option::PARAMETER_NONE mode.');
    }

    if ($this->isArray())
    {
      if (null === $default)
      {
        $default = array();
      }
      elseif (!is_array($default))
      {
        throw new \LogicException('A default value for an array option must be an array.');
      }
    }

    $this->default = $this->acceptParameter() ? $default : false;
  }

  /**
   * Returns the default value.
   *
   * @return mixed The default value
   */
  public function getDefault()
  {
    return $this->default;
  }

  /**
   * Returns the description text.
   *
   * @return string The description text
   */
  public function getDescription()
  {
    return $this->description;
  }
}
