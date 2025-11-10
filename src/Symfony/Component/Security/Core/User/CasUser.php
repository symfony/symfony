<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\User;

/**
 * UserInterface implementation used by the access-token security workflow with a CAS server.
 */
class CasUser implements UserInterface
{
    public function __construct(
        private readonly string $userIdentifier,
        private readonly array $roles = ['ROLE_USER'],
    ) {
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    /**
     * @deprecated since Symfony 7.3
     */
    #[\Deprecated(since: 'symfony/security-core 7.3')]
    public function eraseCredentials(): void
    {
        if (\PHP_VERSION_ID < 80400) {
            @trigger_error(\sprintf('Method %s::eraseCredentials() is deprecated since symfony/security-core 7.3', self::class), \E_USER_DEPRECATED);
        }
    }
}
