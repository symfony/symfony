<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Messenger;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RunCommandFailedException;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class RunCommandMessage implements \Stringable
{
    /**
     * @param bool       $throwOnFailure  If the command has a non-zero exit code, throw {@see RunCommandFailedException}; a command killed by $timeout throws whatever this is set to
     * @param bool       $catchExceptions @see Application::setCatchExceptions(); ignored when $isolated is true, as the command then catches its own exceptions
     * @param bool       $isolated        Whether to run the command in a separate PHP process instead of the one handling the message
     * @param float|null $timeout         The number of seconds the command may run for before it is killed, or null to let it run to completion; requires $isolated
     */
    public function __construct(
        public readonly string $input,
        public readonly bool $throwOnFailure = true,
        public readonly bool $catchExceptions = false,
        public readonly bool $isolated = false,
        public readonly ?float $timeout = null,
    ) {
        if (null !== $timeout && !$isolated) {
            throw new InvalidArgumentException(\sprintf('A timeout can only be set on an isolated command, as command "%s" cannot be interrupted while it runs in the current process.', $input));
        }

        if (null !== $timeout && $timeout <= 0) {
            throw new InvalidArgumentException(\sprintf('The timeout of command "%s" must be a positive number of seconds, or null to let the command run to completion.', $input));
        }
    }

    public function __toString(): string
    {
        return $this->input;
    }
}
