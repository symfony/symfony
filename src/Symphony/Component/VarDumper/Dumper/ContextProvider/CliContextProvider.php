<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\VarDumper\Dumper\ContextProvider;

/**
 * Tries to provide context on CLI.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
final class CliContextProvider implements ContextProviderInterface
{
    public function getContext(): ?array
    {
        if ('cli' !== PHP_SAPI) {
            return null;
        }

        return array(
            'command_line' => $commandLine = implode(' ', $_SERVER['argv']),
            'identifier' => hash('crc32b', $commandLine.$_SERVER['REQUEST_TIME_FLOAT']),
        );
    }
}
