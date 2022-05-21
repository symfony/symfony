<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Token;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\SignedTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\TokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\TokenExtractor\BearerTokenExtractorInterface;

/**
 * @author Vincent Chalamon <vincentchalamon@gmail.com>
 *
 * @final
 */
class HttpBearerAuthenticator extends AbstractBearerAuthenticator
{
    private readonly Configuration $configuration;

    public function __construct(UserProviderInterface $userProvider, BearerTokenExtractorInterface $tokenExtractor, Configuration $configuration, string $realmName, string $payloadKey, LoggerInterface $logger = null)
    {
        parent::__construct($userProvider, $tokenExtractor, $realmName, $payloadKey, $logger);

        $this->configuration = $configuration;
    }

    /**
     * Override Passport to add SignedTokenBadge.
     */
    public function authenticate(Request $request): Passport
    {
        $passport = parent::authenticate($request);

        return $passport->addBadge(new SignedTokenBadge($this->configuration, $passport->getBadge(TokenBadge::class)->getToken()));
    }

    protected function getToken(string $data): Token
    {
        return $this->configuration->parser()->parse($data);
    }
}
