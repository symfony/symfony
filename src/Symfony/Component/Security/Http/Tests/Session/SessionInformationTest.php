<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Session;

use Symfony\Component\Security\Http\Session\SessionInformation;

/**
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class SessionInformationTest extends \PHPUnit_Framework_TestCase
{
    public function testExpiration()
    {
        $sessionInfo = $this->getSessionInformation();
        $this->assertFalse($sessionInfo->isExpired());
        $sessionInfo->expireAt(time() - 1);

        $this->assertTrue($sessionInfo->isExpired());
    }

    public function testUpdateLastUsed()
    {
        $now = time() - 5;
        $sessionInfo = $this->getSessionInformation();
        $this->assertNotEquals($now, $sessionInfo->getLastUsed());
        $sessionInfo->updateLastUsed($now);
        $this->assertEquals($now, $sessionInfo->getLastUsed());
    }

    public function testGetLastUsed()
    {
        $sessionInfo = $this->getSessionInformation();
        $this->assertLessThan(microtime(true), $sessionInfo->getLastUsed());
    }

    public function testGetSessionId()
    {
        $sessionInfo = $this->getSessionInformation();
        $this->assertEquals('bar', $sessionInfo->getSessionId());
    }

    public function testGetUsername()
    {
        $sessionInfo = $this->getSessionInformation();
        $this->assertEquals('foo', $sessionInfo->getUsername());
    }

    /**
     * @return \Symfony\Component\Security\Http\Session\SessionInformation
     */
    private function getSessionInformation()
    {
        return new SessionInformation('foo', 'bar', time());
    }
}
