<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Handler;

/**
 * @internal
 */
class ResultWrappedHandler
{
    public function __construct(private readonly \Closure $handler)
    {
    }

    public function __invoke(Result $r, object ...$messages): int
    {
        try {
            $lastResult = ($this->handler)(...$messages);
        } catch (\Throwable $e) {
            foreach ($messages as $message) {
                $r->error($message, $e);
            }

            return \count($messages);
        }

        $length = \count($messages);
        $lastMessage = \array_pop($messages);

        foreach ($messages as $message) {
            $r->ok($message);
        }

        $r->ok($lastMessage, $lastResult);

        return $length;
    }
}
