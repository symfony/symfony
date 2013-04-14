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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class CommandDescription
{
    /**
     * @var Command
     */
    private $command;
    
    /**
     * @param Command $command
     */
    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->command->getName();
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->command->getDescription();
    }

    /**
     * @return array
     */
    public function getAliases()
    {
        return $this->command->getAliases();
    }

    /**
     * @return string
     */
    public function getSynopsis()
    {
        return $this->command->getSynopsis();
    }

    /**
     * @return string
     */
    public function getHelp()
    {
        return $this->command->getProcessedHelp();
    }

    /**
     * @return InputDefinition
     */
    public function getDefinition()
    {
        // ensure command definition is merged with application one
        $method = new \ReflectionMethod($this->command, 'mergeApplicationDefinition');
        $method->setAccessible(true);
        $method->invoke($this->command, false);
        
        // reads native definition
        $method = new \ReflectionMethod($this->command, 'getNativeDefinition');
        $method->setAccessible(true);

        return $method->invoke($this->command);
    }
}
