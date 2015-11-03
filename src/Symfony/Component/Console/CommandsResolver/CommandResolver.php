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
class CommandResolver implements CommandResolverInterface
{
    private $commands = array();

    /**
     * {@inheritdoc}
     */
    public function add(Command $command)
    {
        $this->commands[$command->getName()] = $command;
  
        foreach ($command->getAliases() as $alias) {
            $this->commands[$alias] = $command;
        }
  
    }
  
    /**
     * {@inheritdoc}
     */
    public function has($name)
    {
        return !empty($this->commands[$name]);
    }
  
    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        return !empty($this->commands[$name]) ? $this->commands[$name] : null;
    }
  
    /**
     * {@inheritdoc}
     */
    public function getAll()
    {
        return $this->commands;
    }
  
    /**
     * {@inheritdoc}
     */
    public function getAllNames()
    {
        return array_keys($this->commands);
    }
}
