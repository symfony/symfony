<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Exception;

/**
 * Exception thrown when rendering fails.
 *
 * Typically thrown when a component renders a line that exceeds the terminal width.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RenderException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $lineNumber = 0,
        private readonly int $lineWidth = 0,
        private readonly int $terminalWidth = 0,
    ) {
        parent::__construct($message);
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    public function getLineWidth(): int
    {
        return $this->lineWidth;
    }

    public function getTerminalWidth(): int
    {
        return $this->terminalWidth;
    }
}
