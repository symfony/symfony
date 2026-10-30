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
 * What a blocked program waits for from the user (OSC 7501).
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
enum ProgramBlockKind: string
{
    /**
     * Approval to do something.
     */
    case Permission = 'permission';

    /**
     * An answer the user must type.
     */
    case Question = 'question';

    /**
     * A login, token, or credential.
     */
    case Auth = 'auth';
}
