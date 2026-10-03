<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Io\Terminal;

// These definitions describe the part of ext-terminal 1.x that Console and TUI call, so that static analysis checks those calls instead of skipping them.

final class Terminal
{
    public static function create(): self
    {
    }

    /**
     * @param resource      $input
     * @param resource|null $output
     */
    public static function fromStreams($input, $output = null): self
    {
    }

    public function isTty(): bool
    {
    }

    public function getSize(): TerminalSize|false
    {
    }

    public function enableRawMode(): ModeToken|false
    {
    }

    public function restoreMode(?ModeToken $mode = null): bool
    {
    }

    public function readEvent(?float $timeout = null): array|false
    {
    }

    public function readSecret(string $prompt = ''): string
    {
    }
}

final readonly class TerminalSize
{
    public int $cols;
    public int $rows;
}

final class ModeToken
{
}
