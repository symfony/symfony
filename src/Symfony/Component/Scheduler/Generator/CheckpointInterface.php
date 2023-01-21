<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Generator;

/**
 * @experimental
 */
interface CheckpointInterface
{
    public function acquire(\DateTimeImmutable $now): bool;

    public function time(): \DateTimeImmutable;

    public function index(): int;

    public function save(\DateTimeImmutable $time, int $index): void;

    public function release(\DateTimeImmutable $now, ?\DateTimeImmutable $nextTime): void;
}
