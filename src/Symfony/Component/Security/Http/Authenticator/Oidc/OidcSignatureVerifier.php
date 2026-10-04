<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Oidc;

use Jose\Component\Checker;
use Jose\Component\Core\Algorithm;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Signature\JWSTokenSupport;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\OAuth2\JwsAlgorithms;
use Symfony\Component\Security\Http\Oidc\OidcProviderKeys;

/**
 * Verifies the signature of an OIDC ID token against the keys of the provider.
 *
 * Which keys those are, and for how long they are cached, is the job of
 * {@see OidcProviderKeys}, which an application acting as a resource server reads the
 * same key set from. Verifying the signature is what OIDC Core 1.0, Section 3.1.3.7,
 * item 6 allows to replace by the transport security of the token endpoint request:
 * doing it anyway keeps the guarantee independent of the TLS configuration of the
 * HTTP client in use.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class OidcSignatureVerifier
{
    /**
     * @param list<string> $algorithms The signature algorithms accepted to verify the ID token (e.g. ["RS256"])
     */
    public function __construct(
        private readonly OidcProviderKeys $providerKeys,
        private readonly array $algorithms = ['RS256'],
    ) {
    }

    /**
     * Verifies the signature of the given ID token and returns its claims.
     *
     * The claims are only structurally valid at this point: they still have to be
     * validated by {@see OidcIdToken::validateClaims()}.
     *
     * @return array<string, mixed> The claims of the verified ID token
     *
     * @throws AuthenticationException If the signature cannot be verified
     */
    public function verify(string $idToken): array
    {
        if (!class_exists(JWSVerifier::class) || !class_exists(Checker\HeaderCheckerManager::class)) {
            throw new \LogicException('You cannot verify OIDC ID token signatures since the "web-token/jwt-library" package is not installed. Try running "composer require web-token/jwt-library".');
        }

        $algorithms = $this->createAlgorithmManager();

        try {
            $jws = (new JWSSerializerManager([new CompactSerializer()]))->unserialize($idToken);
        } catch (\InvalidArgumentException $e) {
            throw new AuthenticationException('Invalid ID token format.', previous: $e);
        }

        // The "alg" header is checked first, and required: JWSVerifier throws on an
        // algorithm it does not know, "none" among them, instead of reporting a failed
        // verification. Checking it here also rejects a token announcing an algorithm
        // the provider is not configured for before any request is sent to its JWKS.
        try {
            (new Checker\HeaderCheckerManager([new Checker\AlgorithmChecker($algorithms->list())], [new JWSTokenSupport()]))->check($jws, 0, ['alg']);
        } catch (Checker\InvalidHeaderException|Checker\MissingMandatoryHeaderParameterException $e) {
            throw new AuthenticationException(\sprintf('The ID token is not signed with any of the expected algorithms ("%s").', implode('", "', $algorithms->list())), previous: $e);
        }

        $kid = $jws->getSignature(0)->hasProtectedHeaderParameter('kid') ? $jws->getSignature(0)->getProtectedHeaderParameter('kid') : null;
        if (null !== $kid && !\is_string($kid)) {
            throw new AuthenticationException('The ID token "kid" header must be a string.');
        }

        $jwkSet = $this->providerKeys->getSignatureKeySet($kid);
        if (!\count($jwkSet)) {
            throw new AuthenticationException('The OIDC provider published no signing key usable to verify the ID token signature.');
        }

        $jwsVerifier = new JWSVerifier($algorithms);

        try {
            // the component supports web-token/jwt-library 3.x too, where verify() does not
            // exist and verifyWithKeySet() is not deprecated yet; static analysis only ever
            // sees the newest version, hence the two ignores
            if (method_exists($jwsVerifier, 'verify')) { // @phpstan-ignore function.alreadyNarrowedType
                $verified = $jwsVerifier->verify($jws, $jwkSet, 0)->isVerified();
            } else {
                $verified = $jwsVerifier->verifyWithKeySet($jws, $jwkSet, 0); // @phpstan-ignore method.deprecated
            }
        } catch (\InvalidArgumentException $e) {
            throw new AuthenticationException('The ID token signature could not be verified.', previous: $e);
        }

        if (!$verified) {
            throw new AuthenticationException('The ID token signature is invalid.');
        }

        try {
            $claims = json_decode($jws->getPayload() ?? '', true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new AuthenticationException('Invalid ID token payload.');
        }

        if (!\is_array($claims)) {
            throw new AuthenticationException('Invalid ID token payload.');
        }

        return $claims;
    }

    private function createAlgorithmManager(): AlgorithmManager
    {
        // no MAC algorithm is accepted, so a public key published by the provider can never
        // be turned into the shared secret of an "HS256" token (key confusion)
        return new AlgorithmManager(array_map(static function (string $name): Algorithm {
            if (!isset(JwsAlgorithms::ASYMMETRIC[$name])) {
                throw new \LogicException(\sprintf('Unsupported OIDC ID token signature algorithm "%s". Supported algorithms are: "%s".', $name, implode('", "', array_keys(JwsAlgorithms::ASYMMETRIC))));
            }

            return new (JwsAlgorithms::ASYMMETRIC[$name])();
        }, $this->algorithms));
    }
}
