<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Terminal\ProgramStatus;

/**
 * What a program tells the terminal it is doing (OSC 7501).
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
enum ProgramState: string
{
    /**
     * At rest, waiting for the user's next instruction.
     */
    case Idle = 'idle';

    /**
     * Running.
     */
    case Working = 'working';

    /**
     * Finished a piece of work whose result the user has not seen yet.
     */
    case Done = 'done';

    /**
     * Cannot continue until the user does something.
     */
    case Blocked = 'blocked';

    /**
     * Failed and stopped.
     */
    case Error = 'error';
}
