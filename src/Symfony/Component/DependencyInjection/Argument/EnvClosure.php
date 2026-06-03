<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Argument;

use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class EnvClosure implements \Stringable
{
    public function __construct(
        private \Closure $closure,
        private mixed $default = null,
    ) {
    }

    public function __invoke(): mixed
    {
        try {
            return ($this->closure)();
        } catch (EnvNotFoundException $e) {
            return $this->default ?? throw $e;
        }
    }

    public function __toString(): string
    {
        return $this->__invoke();
    }
}
