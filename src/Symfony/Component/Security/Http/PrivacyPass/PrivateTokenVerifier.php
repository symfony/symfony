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

use Symfony\Component\Security\Http\PrivacyPass\Exception\InvalidPrivateTokenException;

/**
 * Verifies that a redeemed token answers a challenge this origin sent and was issued by an issuer this origin accepts.
 *
 * Replays are not this class' business: an origin that gives a token a side effect also has to keep the nonce of every token it accepted, for as long as the challenge it answers is accepted.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9577#section-2.2.3
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class PrivateTokenVerifier
{
    /**
     * @param iterable<TokenChallenge>    $challenges The challenges this origin accepts a token for
     * @param iterable<TokenKeyInterface> $tokenKeys  The issuer public keys this origin accepts a token from
     *
     * @return TokenChallenge The challenge the token answers
     *
     * @throws InvalidPrivateTokenException If the token answers none of the challenges, names none of the keys or carries an invalid authenticator
     */
    public function verify(PrivateToken $token, iterable $challenges, iterable $tokenKeys): TokenChallenge
    {
        $challenge = null;
        foreach ($challenges as $candidate) {
            if ($candidate->tokenType->getValue() === $token->tokenType->getValue() && hash_equals($candidate->getDigest(), $token->challengeDigest)) {
                $challenge = $candidate;
                break;
            }
        }

        if (null === $challenge) {
            throw new InvalidPrivateTokenException('The token answers no challenge this origin accepts.');
        }

        $tokenKey = null;
        foreach ($tokenKeys as $candidate) {
            if (hash_equals($candidate->getId(), $token->tokenKeyId)) {
                $tokenKey = $candidate;
                break;
            }
        }

        if (null === $tokenKey) {
            throw new InvalidPrivateTokenException('The token names a key this origin does not accept.');
        }

        if (!$tokenKey->verify($token->getAuthenticatorInput(), $token->authenticator)) {
            throw new InvalidPrivateTokenException('The token carries an invalid authenticator.');
        }

        return $challenge;
    }
}
