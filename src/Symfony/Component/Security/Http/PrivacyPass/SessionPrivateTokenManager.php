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
 * Remembers in the session that a token was accepted, so that the next requests of that session are accepted without one.
 *
 * A client is challenged on a request it can replay, typically the GET of a form, and the request the form is submitted with reads the session instead of asking for a second token.
 * It also gives a client that answers no challenge one CAPTCHA to solve instead of one per request.
 *
 * The session already links the requests it carries, so remembering the verdict there tells the origin nothing it could not already tell.
 * Nothing of the token itself is kept.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class SessionPrivateTokenManager implements PrivateTokenManagerInterface
{
    /**
     * @param int    $lifetime   How long a session is accepted without a token, in seconds
     * @param string $sessionKey Where the verdict is kept in the session
     */
    public function __construct(
        private readonly PrivateTokenManagerInterface $manager,
        private readonly int $lifetime = 1800,
        private readonly string $sessionKey = '_security.private_token',
        private readonly ?ClockInterface $clock = null,
    ) {
        if (1 > $lifetime) {
            throw new \InvalidArgumentException(\sprintf('The lifetime of an accepted session must be at least 1 second, "%d" given.', $lifetime));
        }
    }

    public function isTokenValid(Request $request): bool
    {
        $now = $this->clock?->now()->getTimestamp() ?? time();

        // the session is only read when the request brought one, so that a visitor without a token
        // is not given a session cookie by the challenge alone
        if ($request->hasPreviousSession() && $now < $request->getSession()->get($this->sessionKey, 0)) {
            return true;
        }

        if (!$this->manager->isTokenValid($request)) {
            return false;
        }

        if ($request->hasSession()) {
            $request->getSession()->set($this->sessionKey, $now + $this->lifetime);
        }

        return true;
    }

    public function getChallengeHeader(Request $request): string
    {
        return $this->manager->getChallengeHeader($request);
    }
}
