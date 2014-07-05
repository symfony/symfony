<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Login;

use Symfony\Component\Security\Core\User\UserInterface;

interface LoginManagerInterface
{
    public function loginUser($firewallName, UserInterface $user);
}
