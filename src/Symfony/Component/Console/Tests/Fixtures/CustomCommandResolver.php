<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Fixtures;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandsResolver\CommandResolverInterface;

/**
 * @author Ivan Shcherbak <dev@funivan.com>
 */
class CustomCommandResolver implements CommandResolverInterface
{
  /**
   * Cache commands.
   *
   * @var array
   */
  private $commands = array();

  /**
   * @var array
   */
  private static $commandsMap = array(
    'lazyTest' => '\Symfony\Component\Console\Tests\Fixtures\LazyTestCommand',
  );

  /**
   * @param string $commandClassName
   *
   * @return string|null
   */
  public static function getNameFromClass($commandClassName)
  {
      $commandClassName = '\\'.ltrim($commandClassName, '\\');
      $name = array_search($commandClassName, self::$commandsMap);
      if ($name === false) {
          return;
      }

      return $name;
  }

  /**
   * @param string $name
   *
   * @return string|null
   */
  public static function getClassFromName($name)
  {
      return isset(self::$commandsMap[$name]) ? self::$commandsMap[$name] : null;
  }

  /**
   * @param Command $command
   *
   * @return $this
   */
  public function add(Command $command)
  {
      $this->commands[$command->getName()] = $command;

      return $this;
  }

  /**
   * Check if command exist.
   *
   * @param string $name
   *
   * @return bool
   */
  public function has($name)
  {
      if (isset($this->commands[$name])) {
          return true;
      }

      return isset(self::$commandsMap[$name]);
  }

  /**
   * Get command by name or alias.
   *
   * @param string $name
   *
   * @return Command|null
   */
  public function get($name)
  {
      if (isset($this->commands[$name])) {
          return $this->commands[$name];
      }

      $class = self::getClassFromName($name);
      if (empty($class)) {
          return;
      }

      return new $class();
  }

  /**
   * Return all available commands related to this application.
   *
   * @return Command[]
   */
  public function getAll()
  {
      $items = $this->commands;
      foreach (self::$commandsMap as $command) {
          $items[] = new $command();
      }

      return $items;
  }

  /**
   * Return all command names and aliases.
   *
   * @return array
   */
  public function getAllNames()
  {
      $names = array_keys(self::$commandsMap);

      if (!empty($this->commands)) {
          $names = array_merge($names, array_keys($this->commands));
      }

      return $names;
  }
}
