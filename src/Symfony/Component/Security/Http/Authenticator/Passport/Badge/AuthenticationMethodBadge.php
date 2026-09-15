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

use Symfony\Component\Security\Core\Authentication\AuthenticationMethod;
use Symfony\Component\Security\Http\EventListener\AuthenticationProofsListener;

/**
 * States which authentication methods the authenticator verified for this attempt.
 *
 * {@see AuthenticationProofsListener} records them on the token, as AuthenticationMethod
 * values mapped to the moment the attempt succeeded, which is what IS_AUTHENTICATED_RECENTLY
 * and any stricter level of assurance policy are decided on. Without this badge, the proof of
 * an interactive login is recorded under AuthenticationMethod::UNSPECIFIED.
 *
 * @final
 */
class AuthenticationMethodBadge implements BadgeInterface
{
    /**
     * @var string[]
     */
    public readonly array $methods;

    /**
     * @param string ...$methods AuthenticationMethod values, at least one
     */
    public function __construct(string ...$methods)
    {
        if (!$methods) {
            throw new \InvalidArgumentException('At least one authentication method is required.');
        }

        $this->methods = array_values(array_unique($methods));
    }

    public function isResolved(): bool
    {
        return true;
    }
}
