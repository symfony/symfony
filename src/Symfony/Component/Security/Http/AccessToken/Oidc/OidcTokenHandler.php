<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\AccessToken\Oidc;

use Jose\Component\Checker;
use Jose\Component\Checker\ClaimCheckerManager;
use Jose\Component\Core\Algorithm;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\JWSTokenSupport;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\AccessToken\Oidc\Exception\InvalidSignatureException;
use Symfony\Component\Security\Http\AccessToken\Oidc\Exception\MissingClaimException;
use Symfony\Component\Security\Http\Authenticator\FallbackUserLoader;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * The token handler decodes and validates the token, and retrieves the user identifier from it.
 *
 * @experimental
 */
final class OidcTokenHandler implements AccessTokenHandlerInterface
{
    use OidcTrait;

    public function __construct(
        private Algorithm $signatureAlgorithm,
        private JWK $jwk,
        private string $audience,
        private array $issuers,
        private string $claim = 'sub',
        private ?LoggerInterface $logger = null,
        private ClockInterface $clock = new Clock()
    ) {
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        if (!class_exists(JWSVerifier::class) || !class_exists(Checker\HeaderCheckerManager::class)) {
            throw new \LogicException('You cannot use the "oidc" token handler since "web-token/jwt-signature" and "web-token/jwt-checker" are not installed. Try running "composer require web-token/jwt-signature web-token/jwt-checker".');
        }

        try {
            // Decode the token
            $jwsVerifier = new JWSVerifier(new AlgorithmManager([$this->signatureAlgorithm]));
            $serializerManager = new JWSSerializerManager([new CompactSerializer()]);
            $jws = $serializerManager->unserialize($accessToken);
            $claims = json_decode($jws->getPayload(), true);

            // Verify the signature
            if (!$jwsVerifier->verifyWithKey($jws, $this->jwk, 0)) {
                throw new InvalidSignatureException();
            }

            // Verify the headers
            $headerCheckerManager = new Checker\HeaderCheckerManager([
                new Checker\AlgorithmChecker([$this->signatureAlgorithm->name()]),
            ], [
                new JWSTokenSupport(),
            ]);
            // if this check fails, an InvalidHeaderException is thrown
            $headerCheckerManager->check($jws, 0);

            // Verify the claims
            $checkers = [
                new Checker\IssuedAtChecker(0, false, $this->clock),
                new Checker\NotBeforeChecker(0, false, $this->clock),
                new Checker\ExpirationTimeChecker(0, false, $this->clock),
                new Checker\AudienceChecker($this->audience),
                new Checker\IssuerChecker($this->issuers),
            ];
            $claimCheckerManager = new ClaimCheckerManager($checkers);
            // if this check fails, an InvalidClaimException is thrown
            $claimCheckerManager->check($claims);

            if (empty($claims[$this->claim])) {
                throw new MissingClaimException(sprintf('"%s" claim not found.', $this->claim));
            }

            // UserLoader argument can be overridden by a UserProvider on AccessTokenAuthenticator::authenticate
            return new UserBadge($claims[$this->claim], new FallbackUserLoader(fn () => $this->createUser($claims)), $claims);
        } catch (\Exception $e) {
            $this->logger?->error('An error occurred while decoding and validating the token.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new BadCredentialsException('Invalid credentials.', $e->getCode(), $e);
        }
    }
}
