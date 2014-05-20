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
 * Interface used by the Discovery class for creating commands.
 *
 * @author Alberto Garcia Lamela <alberto.garcial@hotmail.com>
 *
 * @api
 */
Interface CommandFactoryInterface
{

    /**
     * Creates a command given an array definition.
     *
     * @param $commandDefinition Array
     * @return Instance of a class extending Command Class.
     *
     * @api
     */
    public function createCommand(array $commandDefinition);

}
