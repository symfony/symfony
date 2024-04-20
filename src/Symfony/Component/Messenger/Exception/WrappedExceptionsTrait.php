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
 * @author Jeroen <https://github.com/Jeroeny>
 *
 * @internal
 */
trait WrappedExceptionsTrait
{
    private array $exceptions;

    /**
     * @return \Throwable[]
     */
    public function getWrappedExceptions(?string $class = null, bool $recursive = false): array
    {
        return $this->getWrappedExceptionsRecursively($class, $recursive, $this->exceptions);
    }

    /**
     * @param class-string<\Throwable>|null $class
     * @param iterable<\Throwable>          $exceptions
     *
     * @return \Throwable[]
     */
    private function getWrappedExceptionsRecursively(?string $class, bool $recursive, iterable $exceptions): array
    {
        $unwrapped = [];
        foreach ($exceptions as $key => $exception) {
            if ($recursive && $exception instanceof WrappedExceptionsInterface) {
                $unwrapped[] = $this->getWrappedExceptionsRecursively($class, $recursive, $exception->getWrappedExceptions());

                continue;
            }

            if ($class && !is_a($exception, $class)) {
                continue;
            }

            $unwrapped[] = [$key => $exception];
        }

        return array_merge(...$unwrapped);
    }
}
