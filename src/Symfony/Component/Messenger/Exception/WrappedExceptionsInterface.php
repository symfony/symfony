<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Exception;

/**
 * Exception that holds multiple exceptions thrown by one or more handlers and/or messages.
 *
 * @author Jeroen <https://github.com/Jeroeny>
 */
interface WrappedExceptionsInterface extends \Throwable
{
    /**
     * @template TClass of class-string<\Throwable>
     *
     * @param TClass|null $class
     *
     * @return \Throwable[]
     *
     * @psalm-return (TClass is null ? \Throwable[] : TClass[])
     */
    public function getWrappedExceptions(?string $class = null, bool $recursive = false): array;
}
