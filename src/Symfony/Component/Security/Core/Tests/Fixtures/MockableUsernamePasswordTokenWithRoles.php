<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Fixtures;

use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class MockableUsernamePasswordTokenWithRoles extends UsernamePasswordToken
{
    public function getRoles(): array
    {
        return [];
    }
}
