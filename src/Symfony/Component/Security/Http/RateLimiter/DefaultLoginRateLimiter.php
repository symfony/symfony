<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\RateLimiter;

use Symfony\Component\HttpFoundation\RateLimiter\AbstractRequestRateLimiter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Security;

/**
 * A default login throttling limiter.
 *
 * This limiter prevents breadth-first attacks by enforcing
 * a limit on username+IP and a (higher) limit on IP.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @experimental in 5.2
 */
final class DefaultLoginRateLimiter extends AbstractRequestRateLimiter
{
    private $globalFactory;
    private $localFactory;

    public function __construct(RateLimiterFactory $globalFactory, RateLimiterFactory $localFactory)
    {
        $this->globalFactory = $globalFactory;
        $this->localFactory = $localFactory;
    }

    protected function getLimiters(Request $request): array
    {
        return [
            $this->globalFactory->create($request->getClientIp()),
            $this->localFactory->create($request->attributes->get(Security::LAST_USERNAME).$request->getClientIp()),
        ];
    }
}
