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
use Symfony\Component\Console\CommandsResolver\CommandResolver;

/**
 * @author Ivan Shcherbak <dev@funivan.com>
 */
class CustomCommandResolver extends CommandResolver
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
     * {@inheritdoc}
     */
    public function has($name)
    {
        if (parent::has($name)) {
            return true;
        }

        return isset(self::$commandsMap[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        $command = parent::get($name);
        if ($command) {
            return $command;
        }

        $class = self::getClassFromName($name);
        if (empty($class)) {
            return;
        }

      /** @var Command $command */
      $command = new $class();
        # Do not create command on next request. Register it in current command resolver
        $this->add($command);

        return $command;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
