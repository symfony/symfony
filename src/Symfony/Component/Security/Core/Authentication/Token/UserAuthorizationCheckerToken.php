<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Token;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * UserAuthorizationCheckerToken implements a token used for checking authorization.
 *
 * @author Nate Wiebe <nate@northern.co>
 *
 * @internal
 */
final class UserAuthorizationCheckerToken extends AbstractToken implements OfflineTokenInterface
{
    public function __construct(UserInterface $user)
    {
        parent::__construct($user->getRoles());

        $this->setUser($user);
    }
}
