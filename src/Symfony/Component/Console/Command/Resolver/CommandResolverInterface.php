<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Command\Resolver;

use Symfony\Component\Console\Command\Command;

/**
 * Command resolver allows to get instance of a Command (or any callable) by name.
 *
 * @author Nikita Konstantinov
 *
 * @api
 */
interface CommandResolverInterface
{
    /**
     * @param string $commandName
     * @return Command|callable
     * @throws CommandResolutionException
     */
    public function resolve($commandName);
}
