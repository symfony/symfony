<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Cooldown;

/**
 * Tells whether a transport is cooling down, and until when if that is known.
 */
final class CooldownStatus
{
    private function __construct(
        private bool $coolingDown,
        private ?\DateTimeImmutable $end = null,
    ) {
    }

    public static function inactive(): self
    {
        return new self(false);
    }

    public static function until(\DateTimeImmutable $end): self
    {
        return new self(true, $end);
    }

    /**
     * The transport is cooling down, until a time that is not known.
     */
    public static function indefinite(): self
    {
        return new self(true);
    }

    public function isCoolingDown(): bool
    {
        return $this->coolingDown;
    }

    /**
     * Returns when the cooldown ends, or null when the transport is not cooling down or the end is not known.
     */
    public function getEnd(): ?\DateTimeImmutable
    {
        return $this->end;
    }
}
