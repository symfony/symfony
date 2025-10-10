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

use Jose\Component\Core\Algorithm;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWKSet;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\Clock;

class OidcTokenGenerator
{
    public function __construct(
        private readonly AlgorithmManager $algorithmManager,
        private readonly JWKSet $jwkset,
        private readonly string $audience,
        private readonly array $issuers,
        private readonly string $claim = 'sub',
        private readonly ClockInterface $clock = new Clock(),
    ) {
    }

    public function generate(string $userIdentifier, ?string $algorithmAlias = null, ?string $issuer = null, ?int $ttl = null, ?\DateTimeImmutable $notBefore = null): string
    {
        $algorithm = $this->getAlgorithm($algorithmAlias);

        if (!$jwk = $this->jwkset->selectKey('sig', $algorithm)) {
            throw new \InvalidArgumentException(\sprintf('No JWK found to sign with "%s" algorithm.', $algorithm->name()));
        }

        $jwsBuilder = new JWSBuilder($this->algorithmManager);

        $now = $this->clock->now();
        $payload = [
            $this->claim => $userIdentifier,
            'iat' => $now->getTimestamp(), # https://datatracker.ietf.org/doc/html/rfc7519#section-4.1.6
            'aud' => $this->audience, # https://datatracker.ietf.org/doc/html/rfc7519#section-4.1.3
            'iss' => $this->getIssuer($issuer), # https://datatracker.ietf.org/doc/html/rfc7519#section-4.1.1
        ];
        if ($ttl) {
            if (0 > $ttl) {
                throw new \InvalidArgumentException('Time to live must be a positive integer.');
            }

            $payload['exp'] = $now->add(new \DateInterval("PT{$ttl}S"))->getTimestamp(); # https://datatracker.ietf.org/doc/html/rfc7519#section-4.1.4
        }
        if ($notBefore) {
            $payload['nbf'] = $notBefore->getTimestamp(); # https://datatracker.ietf.org/doc/html/rfc7519#section-4.1.5
        }

        $jws = $jwsBuilder
            ->create()
            ->withPayload(json_encode($payload, flags: \JSON_THROW_ON_ERROR))
            ->addSignature($jwk, ['alg' => $algorithm->name()])
            ->build();

        $serializer = new CompactSerializer();

        return $serializer->serialize($jws, 0);
    }

    private function getAlgorithm(?string $alias): Algorithm
    {
        if ($alias) {
            if (!$this->algorithmManager->has($alias)) {
                throw new \InvalidArgumentException(sprintf('"%s" is not a valid algorithm. Available algorithms: "%s".', $alias, implode('", "', $this->algorithmManager->list())));
            }
            return $this->algorithmManager->get($alias);
        }

        if (1 !== count($list = $this->algorithmManager->list())) {
            throw new \InvalidArgumentException(sprintf('Please choose an algorithm. Available algorithms: "%s".', implode('", "', $list)));
        }

        return $this->algorithmManager->get($list[0]);
    }

    private function getIssuer(?string $issuer): string
    {
        if ($issuer) {
            if (!in_array($issuer, $this->issuers, true)) {
                throw new \InvalidArgumentException(sprintf('"%s" is not a valid issuer. Available issuers: "%s".', $issuer, implode('", "', $this->issuers)));
            }

            return $issuer;
        }

        if (1 !== count($this->issuers)) {
            throw new \InvalidArgumentException(sprintf('Please choose an issuer. Available issuers: "%s".', implode('", "', $this->issuers)));
        }

        return $this->issuers[0];
    }
}
