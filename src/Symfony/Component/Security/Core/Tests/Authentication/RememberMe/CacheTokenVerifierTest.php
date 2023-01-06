<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication\RememberMe;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Security\Core\Authentication\RememberMe\CacheTokenVerifier;
use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentToken;

class CacheTokenVerifierTest extends TestCase
{
    public function testVerifyCurrentToken()
    {
        $verifier = new CacheTokenVerifier(new ArrayAdapter());
        $token = new PersistentToken('class', 'user', 'series1@special:chars=/', 'value', new \DateTime());
        $this->assertTrue($verifier->verifyToken($token, 'value'));
    }

    public function testVerifyFailsInvalidToken()
    {
        $verifier = new CacheTokenVerifier(new ArrayAdapter());
        $token = new PersistentToken('class', 'user', 'series1@special:chars=/', 'value', new \DateTime());
        $this->assertFalse($verifier->verifyToken($token, 'wrong-value'));
    }

    public function testVerifyOutdatedToken()
    {
        $verifier = new CacheTokenVerifier(new ArrayAdapter());
        $outdatedToken = new PersistentToken('class', 'user', 'series1@special:chars=/', 'value', new \DateTime());
        $newToken = new PersistentToken('class', 'user', 'series1@special:chars=/', 'newvalue', new \DateTime());
        $verifier->updateExistingToken($outdatedToken, 'newvalue', new \DateTime());
        $this->assertTrue($verifier->verifyToken($newToken, 'value'));
    }
}
