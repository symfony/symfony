<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Firewall;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Outcome of a user-refresh attempt performed by {@see ContextListener::refreshUser()}.
 *
 * @internal
 */
final class RefreshUserResult
{
    /**
     * @param list<class-string> $providerClasses
     */
    public function __construct(
        public readonly ?TokenInterface $token,
        public readonly ?string $deauthenticationReason = null,
        public readonly array $providerClasses = [],
    ) {
    }
}
