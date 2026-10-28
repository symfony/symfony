<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Attribute;

/**
 * Marks a controller attribute whose failure the controller can handle on its own.
 *
 * When a listener of the marked attribute throws before the controller runs, the first exception of the chain
 * that is one of the recoverable exceptions is passed to the first controller parameter typed to receive it
 * that has no value yet, instead of being thrown. The parameter type must name one of the recoverable exceptions
 * or a subclass of one: broad types like \Exception receive nothing.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class RecoverableAttribute
{
    /** @var list<class-string<\Throwable>> */
    public readonly array $exceptions;

    /**
     * @param class-string<\Throwable> ...$exceptions
     */
    public function __construct(string ...$exceptions)
    {
        $this->exceptions = array_values($exceptions);
    }
}
