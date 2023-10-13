<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\DataCollector;

final class ObjectParameter
{
    private bool $stringable;
    private string $class;

    public function __construct(
        private readonly object $object,
        private readonly ?\Throwable $error,
    ) {
        $this->stringable = $this->object instanceof \Stringable;
        $this->class = $object::class;
    }

    public function getObject(): object
    {
        return $this->object;
    }

    public function getError(): ?\Throwable
    {
        return $this->error;
    }

    public function isStringable(): bool
    {
        return $this->stringable;
    }

    public function getClass(): string
    {
        return $this->class;
    }
}
