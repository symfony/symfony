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

use Symfony\Component\Security\Http\Authenticator\Oidc\OidcTokens;

/**
 * Carries the OIDC tokens through the authentication passport so they can
 * be transferred to the security token as attributes.
 *
 * @author Mathieu Music <music.music@gmail.com>
 */
final class OidcTokensBadge implements BadgeInterface
{
    public function __construct(
        private readonly OidcTokens $oidcTokens,
    ) {
    }

    public function getOidcTokens(): OidcTokens
    {
        return $this->oidcTokens;
    }

    public function isResolved(): bool
    {
        return true;
    }
}
