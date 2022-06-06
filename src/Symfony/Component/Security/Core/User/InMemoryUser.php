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
 * UserInterface implementation used by the in-memory user provider.
 *
 * This should not be used for anything else.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class InMemoryUser extends User
{
    /**
     * {@inheritdoc}
     *
     * @deprecated since Symfony 5.3
     */
    public function isAccountNonExpired(): bool
    {
        trigger_deprecation('symfony/security-core', '5.3', 'Method "%s()" is deprecated, you should stop using it.', __METHOD__);

        return parent::isAccountNonExpired();
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated since Symfony 5.3
     */
    public function isAccountNonLocked(): bool
    {
        trigger_deprecation('symfony/security-core', '5.3', 'Method "%s()" is deprecated, you should stop using it.', __METHOD__);

        return parent::isAccountNonLocked();
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated since Symfony 5.3
     */
    public function isCredentialsNonExpired(): bool
    {
        trigger_deprecation('symfony/security-core', '5.3', 'Method "%s()" is deprecated, you should stop using it.', __METHOD__);

        return parent::isCredentialsNonExpired();
    }

    /**
     * @deprecated since Symfony 5.3
     */
    public function getExtraFields(): array
    {
        trigger_deprecation('symfony/security-core', '5.3', 'Method "%s()" is deprecated, you should stop using it.', __METHOD__);

        return parent::getExtraFields();
    }

    public function setPassword(string $password)
    {
        trigger_deprecation('symfony/security-core', '5.3', 'Method "%s()" is deprecated, you should stop using it.', __METHOD__);

        parent::setPassword($password);
    }
}
