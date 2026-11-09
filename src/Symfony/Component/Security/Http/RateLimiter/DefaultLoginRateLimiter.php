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
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Security;

/**
 * A default login throttling limiter.
 *
 * This limiter prevents breadth-first attacks by enforcing
 * a limit on username+IP and a (higher) limit on IP.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
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

    /**
     * Clears the username+IP limit only.
     *
     * The IP limit bounds how many usernames a single client may try, so it
     * must survive the successful login of any one of them.
     */
    public function reset(Request $request): void
    {
        $this->createLocalLimiter($request)->reset();
    }

    protected function getLimiters(Request $request): array
    {
        return [
            $this->globalFactory->create($request->getClientIp()),
            $this->createLocalLimiter($request),
        ];
    }

    private function createLocalLimiter(Request $request): LimiterInterface
    {
        $username = $request->attributes->get(Security::LAST_USERNAME, '');
        $username = preg_match('//u', $username) ? mb_strtolower($username, 'UTF-8') : strtolower($username);

        return $this->localFactory->create($username.'-'.$request->getClientIp());
    }
}
