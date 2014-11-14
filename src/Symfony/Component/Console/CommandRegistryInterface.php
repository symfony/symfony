<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console;

/**
 * Class CommandRegistryInterface
 *
 * @author  Yannick Voyer (http://github.com/yvoyer)
 */
interface CommandRegistryInterface
{
    /**
     * Registers custom Commands.
     *
     * @param Application $application An Application instance
     */
    public function registerCommands(Application $application);
}
 