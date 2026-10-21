<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * Represents a previous exception, to dump it without the trace frames that its wrapper also has.
 *
 * The cloner casts the exception separately, so that a direct reference to the same exception keeps the full trace.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class PreviousExceptionStub extends Stub
{
    public function __construct(
        \Throwable $exception,
        public readonly int $sharedFrames,
    ) {
        $this->value = $exception;
    }
}
