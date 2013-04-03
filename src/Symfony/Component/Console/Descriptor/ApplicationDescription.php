<?php

namespace Symfony\Component\Console\Descriptor;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class ApplicationDescription
{
    const GLOBAL_NAMESPACE = '_global';

    private $application;
    private $namespace;
    private $namespaces;
    private $commands;

    public function __construct(Application $application, $namespace = null)
    {
        $this->application = $application;
        $this->namespace = $namespace;
    }

    public function getNamespaces()
    {
        if (null === $this->namespaces) {
            $this->inspectApplication();
        }

        return $this->namespaces;
    }

    public function getCommands()
    {
        if (null === $this->commands) {
            $this->inspectApplication();
        }

        return $this->commands;
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
                    $this->commands[] = $command;
                }

                $names[] = $name;
            }

            $this->namespaces[] = array('id' => $namespace, 'commands' => $names);
        }
    }

    private function sortCommands(array $commands)
    {
        $method = new \ReflectionMethod($this->application, 'sortCommands');
        $method->setAccessible(true);

        return $method->invoke($this->application, $commands);
    }
}
