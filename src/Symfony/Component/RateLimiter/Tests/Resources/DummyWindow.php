<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Tests\Resources;

use Symfony\Component\RateLimiter\LimiterStateInterface;

class DummyWindow implements LimiterStateInterface
{
    private $id;
    private $expirationTime;

    public function __construct(string $id = 'test', ?int $expirationTime = 10)
    {
        $this->id = $id;
        $this->expirationTime = $expirationTime;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getExpirationTime(): ?int
    {
        return $this->expirationTime;
    }
}
