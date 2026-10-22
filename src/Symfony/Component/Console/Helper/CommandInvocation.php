<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Formats command invocations as raw shell text or escaped Console output.
 */
final class CommandInvocation
{
    /**
     * Formats a command invocation as raw shell text.
     *
     * @param Command|string|null $command        The current command, a sibling command name, or null for the launcher alone
     * @param string|null         $fallbackScript The script to use when no Symfony CLI console launcher was detected
     */
    public static function format(Command|string|null $command = null, ?string $fallbackScript = null): string
    {
        $isCli = \in_array(\PHP_SAPI, ['cli', 'phpdbg'], true);
        $currentScript = $_SERVER['PHP_SELF'] ?? $_SERVER['argv'][0] ?? 'bin/console';
        $invocation = $isCli ? [\PHP_BINARY, ...('phpdbg' === \PHP_SAPI ? ['-qrr'] : []), $fallbackScript ?? $currentScript] : ['php', $fallbackScript ?? 'bin/console'];
        $launcher = json_decode($_SERVER['SYMFONY_CLI_CONSOLE_COMMAND'] ?? '');
        $script = $_SERVER['SYMFONY_CLI_CONSOLE_SCRIPT'] ?? '';

        if ($isCli && \is_array($launcher) && 2 === \count($launcher) && \is_string($launcher[0]) && '' !== $launcher[0] && 'console' === $launcher[1] && !str_contains($launcher[0], "\0")) {
            if (\is_string($script) && '' !== $script && !str_contains($script, "\0") && false !== $script = realpath($script)) {
                if ($script === realpath($_SERVER['SCRIPT_FILENAME'] ?? $currentScript)) {
                    $invocation = $launcher;
                }
            }
        }

        if ($command instanceof Command) {
            $command = $command->getApplication()?->isSingleCommand() ? null : $command->getName();
        }
        if (null !== $command) {
            $invocation[] = $command;
        }

        return implode(' ', array_map(self::escapeShellArgument(...), $invocation));
    }

    /**
     * Formats a command invocation for Console output.
     */
    public static function formatEscaped(Command|string|null $command = null, ?string $fallbackScript = null): string
    {
        return OutputFormatter::escape(self::format($command, $fallbackScript));
    }

    private static function escapeShellArgument(string $argument): string
    {
        if ('' === $argument) {
            return '""';
        }
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            return preg_match('{\A[a-zA-Z0-9_./:@+-]+\z}', $argument) ? $argument : "'".str_replace("'", "'\\''", $argument)."'";
        }
        if (!preg_match('/[()%!^"<>&|\s[\]=;*?\'$]/', $argument)) {
            return $argument;
        }
        $argument = preg_replace_callback('/(\\\\*)"/', static fn (array $match) => $match[1].$match[1].'\\"', $argument);
        $argument = preg_replace('/(\\\\+)$/', '$1$1', $argument);

        return '"'.$argument.'"';
    }
}
