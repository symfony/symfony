<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Passport\Badge;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Token;

/**
 * @author Vincent Chalamon <vincentchalamon@gmail.com>
 *
 * @final
 */
class SignedTokenBadge implements BadgeInterface
{
    private readonly Configuration $configuration;

    private readonly Token $payload;

    public function __construct(Configuration $configuration, Token $token)
    {
        $this->configuration = $configuration;
        $this->payload = $token;
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    public function getToken(): Token
    {
        return $this->payload;
    }

    public function isResolved(): bool
    {
        return true;
    }
}
