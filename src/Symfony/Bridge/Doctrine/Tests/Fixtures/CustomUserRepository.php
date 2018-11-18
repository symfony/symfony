<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Fixtures;

use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;

class CustomUserRepository implements UserLoaderInterface
{
    public function loadUserByUsername($username)
    {
        return null;
    }
}
