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

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\PrivacyPass\Exception\InvalidPrivateTokenException;

/**
 * @see https://datatracker.ietf.org/doc/html/rfc9577#section-2.2
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class PrivateTokenManager implements PrivateTokenManagerInterface
{
    private readonly PrivateTokenVerifier $verifier;

    /**
     * @param SpentTokenStoreInterface|null $spentTokens Where the tokens already accepted are kept, or null to accept a token as many times as it is presented
     */
    public function __construct(
        private readonly TokenChallengeFactory $challengeFactory,
        private readonly ?SpentTokenStoreInterface $spentTokens = null,
        ?PrivateTokenVerifier $verifier = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->verifier = $verifier ?? new PrivateTokenVerifier();
    }

    public function isTokenValid(Request $request): bool
    {
        if (null === $value = self::extractToken($request)) {
            return false;
        }

        try {
            $token = PrivateToken::fromBase64Url($value, $this->challengeFactory->getTokenType());
            $challenge = $this->verifier->verify($token, $this->challengeFactory->getChallenges($request), $this->challengeFactory->getTokenKeys());
        } catch (InvalidPrivateTokenException $e) {
            $this->logger?->debug('The Privacy Pass token of the request was rejected.', ['exception' => $e]);

            return false;
        }

        if (null !== $this->spentTokens && !$this->spentTokens->spend($token)) {
            $this->logger?->debug('The Privacy Pass token of the request was already spent.', ['issuer' => $challenge->issuerName]);

            return false;
        }

        return true;
    }

    public function getChallengeHeader(Request $request): string
    {
        return $this->challengeFactory->getChallengeHeader($request);
    }

    /**
     * The value of the "token" authentication parameter of the "Authorization" header, if the request carries one under the "PrivateToken" scheme.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9577#section-2.2.2
     */
    private static function extractToken(Request $request): ?string
    {
        if (!preg_match('/^PrivateToken\s+(.+)$/i', $request->headers->get('Authorization', ''), $credentials)) {
            return null;
        }
        if (!preg_match('/(?:^|[\s,])token\s*=\s*(?:"([^"]*)"|([^\s,]+))/i', $credentials[1], $parameter)) {
            return null;
        }

        return '' !== ($token = '' !== $parameter[1] ? $parameter[1] : ($parameter[2] ?? '')) ? $token : null;
    }
}
