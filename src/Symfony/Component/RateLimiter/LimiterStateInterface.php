<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter;

/**
 * Representing the stored state of the limiter.
 *
 * Classes implementing this interface must be serializable,
 * which is used by the storage implementations to store the
 * object.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
interface LimiterStateInterface
{
    public function getId(): string;

    public function getExpirationTime(): ?int;
}
