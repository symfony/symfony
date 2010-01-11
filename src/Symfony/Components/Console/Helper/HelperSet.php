<?php

namespace Symfony\Components\Console\Helper;

use Symfony\Components\Console\Command\Command;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * HelperSet represents a set of helpers to be used with a command.
 *
 * @package    symfony
 * @subpackage console
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class HelperSet
{
  protected
    $helpers = array(),
    $command = null;

  public function __construct(array $helpers = array())
  {
    foreach ($helpers as $alias => $helper)
    {
      $this->set($helper, is_int($alias) ? null : $alias);
    }
  }

  /**
   * Sets a helper.
   *
   * @param HelperInterface $value The helper instance
   * @param string                    $alias An alias
   */
  public function set(HelperInterface $helper, $alias = null)
  {
    $this->helpers[$helper->getName()] = $helper;
    if (null !== $alias)
    {
      $this->helpers[$alias] = $helper;
    }

    $helper->setHelperSet($this);
  }

  /**
   * Returns true if the helper if defined.
   *
   * @param string  $name The helper name
   *
   * @return Boolean true if the helper is defined, false otherwise
   */
  public function has($name)
  {
    return isset($this->helpers[$name]);
  }

  /**
   * Gets a helper value.
   *
   * @param string $name The helper name
   *
   * @return HelperInterface The helper instance
   *
   * @throws \InvalidArgumentException if the helper is not defined
   */
  public function get($name)
  {
    if (!$this->has($name))
    {
      throw new \InvalidArgumentException(sprintf('The helper "%s" is not defined.', $name));
    }

    return $this->helpers[$name];
  }

  /**
   * Sets the command associated with this helper set.
   *
   * @param Command $command A Command instance
   */
  public function setCommand(Command $command = null)
  {
    $this->command = $command;
  }

  /**
   * Gets the command associated with this helper set.
   *
   * @return Command A Command instance
   */
  public function getCommand()
  {
    return $this->command;
  }
}
