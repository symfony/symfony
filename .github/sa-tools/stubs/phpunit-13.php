<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPUnit\Runner\IssueTriggerResolver;

// These definitions let static analysis keep using PHPUnit 11.5 while checking integrations with PHPUnit 13.1+.

final readonly class Resolution
{
    public function __construct(?string $callee, ?string $caller)
    {
    }

    public function hasCallee(): bool
    {
    }

    public function callee(): ?string
    {
    }

    public function hasCaller(): bool
    {
    }

    public function caller(): ?string
    {
    }
}

interface Resolver
{
    /**
     * @param list<array{function: string, line?: int, file?: string, class?: class-string, type?: '->'|'::', args?: list<mixed>, object?: object}> $trace
     */
    public function resolve(array $trace, string $message): ?Resolution;
}
