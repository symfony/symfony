<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Symfony\Component\Process\Shells;

use Symfony\Component\Process\Contracts\CommandExecutorInterface;

/**
 * ShellCommandExecutor default shell executor.
 *
 * @author Pululu Kinanga Andre <pululuandre@gmail.com>
 */
class ShellCommandExecutor implements CommandExecutorInterface
{
    /**
     * Executes a shell command.
     *
     * @param string $command The shell command to execute.
     * @return string|null The output of the command.
     */
    public function execute(string $command): ?string
    {
        return shell_exec($command);
    }
}
