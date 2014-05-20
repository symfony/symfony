<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\CommandGenerator;

/**
 * Default Implementation of CommandFactoryInterface used by
 * the Discovery class for creating commands.
 *
 * @author Alberto Garcia Lamela <alberto.garcial@hotmail.com>
 *
 * @api
 */
class CommandDefaultFactory implements CommandFactoryInterface
{
    private $customClass;

    /**
     * Constructor.
     *
     * @param string $customClass A string with name of the class
     * we want to create.
     */
    public function __construct($customClass = null)
    {
        $this->customClass = $customClass ? $customClass : null;
    }

    /**
     * @param array $commandDefinition
     * @return Instance of $customClass or '\Symfony\Component\Console\Command\command'.
     *
     * @api
     */
    public function createCommand(array $commandDefinition = array())
    {
        $class = $this->customClass ? $this->customClass : '\Symfony\Component\Console\CommandGenerator\CommandGeneratorBase';

        return new $class($commandDefinition);
    }

}
