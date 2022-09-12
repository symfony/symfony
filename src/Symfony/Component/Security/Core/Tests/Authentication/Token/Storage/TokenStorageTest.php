<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication\Token\Storage;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

class TokenStorageTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @group legacy
     */
    public function testGetSetTokenLegacy()
    {
        $tokenStorage = new TokenStorage();
        $token = new UsernamePasswordToken(new InMemoryUser('username', 'password'), 'provider');
        $tokenStorage->setToken($token);
        $this->assertSame($token, $tokenStorage->getToken());

        $this->expectDeprecation('Since symfony/security-core 6.2: Calling "Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage::setToken()" without any arguments is deprecated, pass null explicitly instead.');

        $tokenStorage->setToken();
        $this->assertNull($tokenStorage->getToken());
    }

    public function testGetSetToken()
    {
        $tokenStorage = new TokenStorage();
        $this->assertNull($tokenStorage->getToken());
        $token = new UsernamePasswordToken(new InMemoryUser('username', 'password'), 'provider');
        $tokenStorage->setToken($token);
        $this->assertSame($token, $tokenStorage->getToken());
        $tokenStorage->setToken(null);
        $this->assertNull($tokenStorage->getToken());
    }
}
