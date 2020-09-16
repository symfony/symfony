<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

/**
 * Apply this stamp to delay delivery of your message on a transport.
 */
final class DelayStamp implements StampInterface
{
    private $delay;

    /**
     * @param int $delay The delay in milliseconds
     */
    public function __construct(int $delay)
    {
        $this->delay = $delay;
    }

    public function getDelay(): int
    {
        return $this->delay;
    }

    public static function delayForSeconds(int $seconds): self
    {
        return new self($seconds * 1000);
    }

    public static function delayForMinutes(int $minutes): self
    {
        return self::delayForSeconds($minutes * 60);
    }

    public static function delayForHours(int $hours): self
    {
        return self::delayForMinutes($hours * 60);
    }
}
