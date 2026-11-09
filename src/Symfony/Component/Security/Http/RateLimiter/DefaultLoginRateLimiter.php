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
 * Usernames are trimmed and case-folded, and their accents, invisible and control characters are removed,
 * so that their variants share the same limit. Removing the accents of precomposed characters
 * and folding compatibility forms like "ⓐ" requires the intl extension or symfony/polyfill-intl-normalizer.
 * Folding "ß" to "ss" requires the mbstring extension on PHP 7.3 or higher.
 *
 * IPv6 clients are limited by their /64 network, since a client commonly gets a whole /64 network.
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
            $this->globalFactory->create(self::getClientNetwork($request)),
            $this->createLocalLimiter($request),
        ];
    }

    private function createLocalLimiter(Request $request): LimiterInterface
    {
        $username = $request->attributes->get(Security::LAST_USERNAME, '');

        if (preg_match('//u', $username)) {
            if (class_exists(\Normalizer::class)) {
                $username = \Normalizer::normalize($username, \Normalizer::FORM_KD) ?: $username;
            }
            $username = \defined('MB_CASE_FOLD') ? mb_convert_case($username, \MB_CASE_FOLD, 'UTF-8') : mb_strtolower($username, 'UTF-8');
            $username = trim(preg_replace('/[\p{Mn}\p{Me}\p{Cf}\p{Cc}]++/u', '', $username));
        } else {
            $username = strtolower(trim($username));
        }

        return $this->localFactory->create($username.'-'.self::getClientNetwork($request));
    }

    private static function getClientNetwork(Request $request): ?string
    {
        $ip = $request->getClientIp();

        if (!filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6) || 0 === strpos($packedIp = inet_pton($ip), "\0\0\0\0\0\0\0\0\0\0\xFF\xFF")) {
            return $ip;
        }

        return inet_ntop(substr($packedIp, 0, 8).str_repeat("\0", 8)).'/64';
    }
}
