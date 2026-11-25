<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Html;

/** @internal */
final class EmbeddedDocumentBudget
{
    private \WeakMap $reservations;
    private int $rootBytes = 0;

    public function __construct(private readonly int $maxBytes, private readonly int $maxDocuments = 64)
    {
        $this->reservations = new \WeakMap();
    }

    public function setRootBytes(int $bytes): void
    {
        $this->rootBytes = $bytes;
    }

    public function allows(object $token): bool
    {
        $used = $this->rootBytes;
        foreach ($this->reservations as $reservation => $bytes) {
            $used += $bytes;
            if ($reservation === $token) {
                return $used <= $this->maxBytes;
            }
        }

        return false;
    }

    public function resize(object $token, int $bytes): bool
    {
        if (!isset($this->reservations[$token])) {
            return false;
        }
        $used = $this->rootBytes;
        foreach ($this->reservations as $reservation => $reserved) {
            $used += $reservation === $token ? $bytes : $reserved;
        }
        if ($used > $this->maxBytes) {
            return false;
        }
        $this->reservations[$token] = $bytes;

        return true;
    }

    public function reserve(int $bytes): ?object
    {
        if (\count($this->reservations) >= $this->maxDocuments) {
            return null;
        }
        $used = $this->rootBytes;
        foreach ($this->reservations as $reserved) {
            $used += $reserved;
        }
        if ($bytes > $this->maxBytes - $used) {
            return null;
        }
        $token = new \stdClass();
        $this->reservations[$token] = $bytes;

        return $token;
    }
}
