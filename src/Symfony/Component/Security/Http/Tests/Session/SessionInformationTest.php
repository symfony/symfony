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
        $sessionInfo->expireNow();

        $this->assertTrue($sessionInfo->isExpired());
    }

    public function testRefreshLastRequest()
    {
        $sessionInfo = $this->getSessionInformation();
        $lastRequest = $sessionInfo->getLastRequest();
        $this->assertInstanceOf('DateTime', $lastRequest);
        $sessionInfo->refreshLastRequest();
        $this->assertGreaterThanOrEqual($lastRequest, $sessionInfo->getLastRequest());
    }

    public function testGetSessionId()
    {
        $sessionInfo = $this->getSessionInformation();
        $this->assertEquals('foo', $sessionInfo->getSessionId());
    }

    public function testGetUsername()
    {
        $sessionInfo = $this->getSessionInformation();
        $this->assertEquals('bar', $sessionInfo->getUsername());
    }

    /**
     * @return \Symfony\Component\Security\Http\Session\SessionInformation
     */
    private function getSessionInformation()
    {
        return new SessionInformation('foo', 'bar', new \DateTime());
    }

}
