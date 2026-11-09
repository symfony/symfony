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
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

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
    private RateLimiterFactory $globalFactory;
    private RateLimiterFactory $localFactory;
    private string $secret;

    /**
     * @param non-empty-string $secret A secret to use for hashing the IP address and username
     */
    public function __construct(RateLimiterFactory $globalFactory, RateLimiterFactory $localFactory, #[\SensitiveParameter] string $secret)
    {
        if (!$secret) {
            throw new InvalidArgumentException('A non-empty secret is required.');
        }

        $this->globalFactory = $globalFactory;
        $this->localFactory = $localFactory;
        $this->secret = $secret;
    }

    /**
     * Clears the username+IP limit, and gives back the token that the successful login took from the IP limit.
     *
     * The IP limit bounds how many usernames a single client may try, so it
     * must survive the successful login of any one of them.
     */
    public function reset(Request $request): void
    {
        $this->createLocalLimiter($request)->reset();
        $this->createGlobalLimiter($request)->consume(-1);
    }

    protected function getLimiters(Request $request): array
    {
        return [
            $this->createGlobalLimiter($request),
            $this->createLocalLimiter($request),
        ];
    }

    private function createGlobalLimiter(Request $request): LimiterInterface
    {
        return $this->globalFactory->create($this->hash($request->getClientIp()));
    }

    private function createLocalLimiter(Request $request): LimiterInterface
    {
        $username = $request->attributes->get(SecurityRequestAttributes::LAST_USERNAME, '');
        $username = preg_match('//u', $username) ? mb_strtolower($username, 'UTF-8') : strtolower($username);

        return $this->localFactory->create($this->hash($username.'-'.$request->getClientIp()));
    }

    private function hash(string $data): string
    {
        return strtr(substr(base64_encode(hash_hmac('sha256', $data, $this->secret, true)), 0, 8), '/+', '._');
    }
}
