<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\RateLimiter;

use Symfony\Component\HttpFoundation\RateLimiter\AbstractRequestRateLimiter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\LimiterInterface;

class MockAbstractRequestRateLimiter extends AbstractRequestRateLimiter
{
    /**
     * @var LimiterInterface[]
     */
    private array $limiters;

    public function __construct(array $limiters)
    {
        $this->limiters = $limiters;
    }

    protected function getLimiters(Request $request): array
    {
        return $this->limiters;
    }
}
