<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Security\Core\User;

/**
 * Class AnonymousUser represents an anonymous (not authenticated) user.
 *
 * @author Ole Rößner <ole@roessner.it>
 */
final class AnonymousUser implements AnonymousUserInterface
{
    /** {@inheritdoc} */
    public function getRoles(): array
    {
        return array();
    }

    /** {@inheritdoc} */
    public function getPassword(): string
    {
        return '';
    }

    /** {@inheritdoc} */
    public function getSalt(): ?string
    {
        return null;
    }

    /** {@inheritdoc} */
    public function getUsername(): string
    {
        return 'anon.';
    }

    /** {@inheritdoc} */
    public function eraseCredentials(): void
    {
    }

    /**
     * Returns the username.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->getUsername();
    }
}
