<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uid;

/**
 * Interface to describe UIDs that sort in the order of their timestamp.
 *
 * Comparing two such UIDs as bytes, as strings or as a database uid column
 * gives the same order as comparing the dates they carry.
 */
interface TimeOrderedUidInterface extends TimeBasedUidInterface
{
    /**
     * Returns the lowest and highest UIDs sharing the timestamp, for range comparisons.
     *
     * Every UID created for the given time sorts between the two bounds:
     *
     *     [$min, $max] = Ulid::createBoundaries($time);
     *     // WHERE ulid BETWEEN :min AND :max
     *
     * The bounds cover the resolution the UID stores: one millisecond for
     * ULID and UUIDv7, one clock tick of 100 nanoseconds for UUIDv6.
     *
     * @return array{static, static}
     */
    public static function createBoundaries(?\DateTimeInterface $time = null): array;
}
