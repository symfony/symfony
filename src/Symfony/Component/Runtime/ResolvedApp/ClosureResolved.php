<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime\ResolvedApp;

use Symfony\Component\Runtime\ResolvedAppInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ClosureResolved implements ResolvedAppInterface
{
    private $closure;
    private $arguments;

    public function __construct(\Closure $closure, array $arguments)
    {
        $this->closure = $closure;
        $this->arguments = $arguments;
    }

    public function __invoke(): object
    {
        if (\is_object($app = ($this->closure)(...$this->arguments))) {
            return $app;
        }

        if (null === $app || \is_string($app) || \is_int($app)) {
            throw new \TypeError(sprintf('The app returned a value of type "%s" but no explicit return-type was found, did you forget to declare one?', get_debug_type($app)));
        }

        throw new \TypeError(sprintf('The app returned a value of type "%s" while an object was expected.', get_debug_type($app)));
    }
}
