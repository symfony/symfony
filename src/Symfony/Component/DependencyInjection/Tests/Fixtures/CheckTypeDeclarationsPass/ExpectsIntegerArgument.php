<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass;

final class ExpectsIntegerArgument
{
    public function __construct(public readonly int $foo) {}

    public static function create(int $foo): self
    {
        return new self($foo);
    }

    public function instance(): self
    {
        return $this;
    }
}
