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
use Symfony\Component\Security\Http\Authenticator\FallbackUserLoader;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * The token handler decodes and validates the token, and retrieves the user identifier from it.
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
        private ClockInterface|LoggerInterface|null $clock = new Clock()
    ) {
        if (!$clock || $clock instanceof LoggerInterface) {
            $this->clock = new Clock();
            trigger_deprecation('symfony/security-http', '6.4', 'Passing a logger to the "%s" constructor is deprecated.', __CLASS__);
        }
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        if (!class_exists(JWSVerifier::class) || !class_exists(Checker\HeaderCheckerManager::class)) {
            throw new \LogicException('You cannot use the "oidc" token handler since "web-token/jwt-signature" and "web-token/jwt-checker" are not installed. Try running "composer require web-token/jwt-signature web-token/jwt-checker".');
        }

        $jwsVerifier = new JWSVerifier(new AlgorithmManager([$this->signatureAlgorithm]));
        $serializerManager = new JWSSerializerManager([new CompactSerializer()]);

        try {
            $jws = $serializerManager->unserialize($accessToken);
            $claims = json_decode($jws->getPayload(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\InvalidArgumentException|\JsonException $e) {
            throw new BadCredentialsException('Unable to parse the token.', 0, $e);
        }

        if (!$jwsVerifier->verifyWithKey($jws, $this->jwk, 0)) {
            throw new BadCredentialsException('The token signature is invalid.');
        }

        $headerCheckerManager = new Checker\HeaderCheckerManager([
            new Checker\AlgorithmChecker([$this->signatureAlgorithm->name()]),
        ], [new JWSTokenSupport()]);

        try {
            $headerCheckerManager->check($jws, 0);
        } catch (Checker\InvalidHeaderException|\InvalidArgumentException $e) {
            throw new BadCredentialsException('The token header is invalid.', 0, $e);
        }

        $claimCheckerManager = new ClaimCheckerManager([
            new Checker\IssuedAtChecker(0, false, $this->clock),
            new Checker\NotBeforeChecker(0, false, $this->clock),
            new Checker\ExpirationTimeChecker(0, false, $this->clock),
            new Checker\AudienceChecker($this->audience),
            new Checker\IssuerChecker($this->issuers),
        ]);

        try {
            $claimCheckerManager->check($claims);
        } catch (Checker\ClaimExceptionInterface $e) {
            throw new BadCredentialsException('At least one of the expected token claims is invalid or missing.', 0, $e);
        }

        if (!($claims[$this->claim] ?? false)) {
            throw new BadCredentialsException(sprintf('The "%s" claim is missing from the token.', $this->claim));
        }

        // UserLoader argument can be overridden by a UserProvider on AccessTokenAuthenticator::authenticate
        return new UserBadge($claims[$this->claim], new FallbackUserLoader(fn () => $this->createUser($claims)), $claims);
    }
}
