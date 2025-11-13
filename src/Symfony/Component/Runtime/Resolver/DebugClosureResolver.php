<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime\Resolver;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DebugClosureResolver extends ClosureResolver
{
    public function resolve(): array
    {
        [$closure, $arguments] = parent::resolve();

        return [
            static function (...$arguments) use ($closure) {
                if (\is_object($app = $closure(...$arguments)) || null === $app) {
                    return $app;
                }

                $r = new \ReflectionFunction($closure);

                throw new \TypeError(\sprintf('Unexpected value of type "%s" returned, "object" expected from "%s" on line "%d".', get_debug_type($app), $r->getFileName(), $r->getStartLine()));
            },
            $arguments,
        ];
    }
}
