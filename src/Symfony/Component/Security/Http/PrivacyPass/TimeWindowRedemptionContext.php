<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\PrivacyPass;

use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Binds the tokens to the time window the challenge was sent in, which an origin rebuilds from its own secret and keeps no state for.
 *
 * The previous window is accepted too, so that a client challenged at the end of a window can still redeem the token it fetched.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9577#section-2.1.1.2
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class TimeWindowRedemptionContext implements RedemptionContextInterface
{
    /**
     * @param string              $secret The secret the context is derived from, which must not be shared with the clients
     * @param int                 $window The lifetime of a context, in seconds
     * @param ClockInterface|null $clock  Any PSR-20 implementation, or null to read the system time
     */
    public function __construct(
        #[\SensitiveParameter] private readonly string $secret,
        private readonly int $window = 3600,
        private readonly ?ClockInterface $clock = null,
    ) {
        if ('' === $secret) {
            throw new \InvalidArgumentException('The secret of the redemption context must not be empty.');
        }
        if (1 > $window) {
            throw new \InvalidArgumentException(\sprintf('The window of the redemption context must be at least 1 second, "%d" given.', $window));
        }
    }

    public function getAcceptedContexts(Request $request): array
    {
        $window = intdiv($this->clock?->now()->getTimestamp() ?? time(), $this->window);

        return [$this->derive($window), $this->derive($window - 1)];
    }

    /**
     * The window a challenge was sent in is accepted until the end of the next one, which is two windows after it started at the earliest.
     */
    public function getLifetime(): ?int
    {
        return 2 * $this->window;
    }

    private function derive(int $window): string
    {
        return hash_hmac('sha256', (string) $window, $this->secret, true);
    }
}
