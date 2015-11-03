<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\CommandsResolver;

use Symfony\Component\Console\Command\Command;

/**
 * @author Ivan Shcherbak <dev@funivan.com>
 */
interface CommandResolverInterface
{
  /**
   * @param Command $command
   *
   * @return $this
   */
  public function add(Command $command);

  /**
   * Check if command exist.
   * 
   * @param string $name
   *
   * @return bool
   */
  public function has($name);

  /**
   * Get command by name or alias.
   * 
   * @param string $name
   *
   * @return Command|null
   */
  public function get($name);

  /**         
   * Return all available commands related to this application.
   * 
   * @return Command[]
   */
  public function getAll();

  /**
   * Return all command names and aliases.
   * 
   * @return array
   */
  public function getAllNames();
}
