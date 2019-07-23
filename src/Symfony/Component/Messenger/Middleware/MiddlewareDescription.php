<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
final class MiddlewareDescription
{
    private $before;
    private $after;

    public static function before(string $before): self
    {
        $self = new self();
        $self->before = $before;

        return $self;
    }

    public static function after(string $after): self
    {
        $self = new self();
        $self->after = $after;

        return $self;
    }

    public static function around(string $before, string $after): self
    {
        $self = new self();
        $self->before = $before;
        $self->after = $after;

        return $self;
    }

    public function getBefore(): ?string
    {
        return $this->before;
    }

    public function getAfter(): ?string
    {
        return $this->after;
    }

    private function __construct()
    {
        // no-op
    }
}
