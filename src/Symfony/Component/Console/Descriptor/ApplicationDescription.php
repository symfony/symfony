<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Descriptor;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class ApplicationDescription
{
    const GLOBAL_NAMESPACE = '_global';

    /**
     * @var Application
     */
    private $application;

    /**
     * @var null|string
     */
    private $namespace;

    /**
     * @var array
     */
    private $namespaces;

    /**
     * @var Command[]
     */
    private $commands;

    /**
     * Constructor.
     *
     * @param Application $application
     * @param string|null $namespace
     */
    public function __construct(Application $application, $namespace = null)
    {
        $this->application = $application;
        $this->namespace = $namespace;
    }

    /**
     * @return array
     */
    public function getNamespaces()
    {
        if (null === $this->namespaces) {
            $this->inspectApplication();
        }

        return $this->namespaces;
    }

    /**
     * @return Command[]
     */
    public function getCommands()
    {
        if (null === $this->commands) {
            $this->inspectApplication();
        }

        return $this->commands;
    }

    /**
     * @param string $name
     *
     * @return Command
     *
     * @throws \InvalidArgumentException
     */
    public function getCommand($name)
    {
        if (!isset($this->commands[$name])) {
            throw new \InvalidArgumentException('Command "'.$name.'" does not exist.');
        }

        return $this->commands[$name];
    }

    private function inspectApplication()
    {
        $this->commands = array();
        $this->namespaces = array();

        $all = $this->application->all($this->namespace ? $this->application->findNamespace($this->namespace) : null);
        foreach ($this->sortCommands($all) as $namespace => $commands) {
            $names = array();

            /** @var Command $command */
            foreach ($commands as $name => $command) {
                if (!$command->getName()) {
                    continue;
                }

                // aliases must be skipped in commands list
                if ($name === $command->getName()) {
                    $this->commands[$name] = $command;
                }

                $names[] = $name;
            }

            $this->namespaces[$namespace] = array('id' => $namespace, 'commands' => $names);
        }
    }

    /**
     * @param array $commands
     *
     * @return array
     */
    private function sortCommands(array $commands)
    {
        $method = new \ReflectionMethod($this->application, 'sortCommands');
        $method->setAccessible(true);

        return $method->invoke($this->application, $commands);
    }
}
