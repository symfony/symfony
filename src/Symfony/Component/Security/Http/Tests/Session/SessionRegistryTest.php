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

use Symfony\Component\Security\Http\Session\SessionRegistry;

/**
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class SessionRegistryTest extends \PHPUnit_Framework_TestCase
{
    public function testGetAllSessions()
    {
        $storage = $this->getSessionRegistryStorage();
        $storage->expects($this->once())->method('getSessionInformations')->with('foo', true);
        $registry = $this->getSessionRegistry($storage);
        $registry->getAllSessions('foo', true);
    }

    public function testGetSessionInformation()
    {
        $storage = $this->getSessionRegistryStorage();
        $storage->expects($this->once())->method('getSessionInformation')->with('foobar');
        $registry = $this->getSessionRegistry($storage);
        $registry->getSessionInformation('foobar');
    }

    public function testRefreshLastRequest()
    {
        $sessionInformation = $this->getSessionInformation();
        $sessionInformation->expects($this->once())->method('refreshLastRequest');
        $storage = $this->getSessionRegistryStorage();
        $storage->expects($this->any())->method('getSessionInformation')->with('foobar')->will($this->returnValue($sessionInformation));
        $storage->expects($this->once())->method('setSessionInformation')->with($sessionInformation);
        $registry = $this->getSessionRegistry($storage);
        $registry->refreshLastRequest('foobar');
    }

    public function testExpireNow()
    {
        $sessionInformation = $this->getSessionInformation();
        $sessionInformation->expects($this->once())->method('expireNow');
        $storage = $this->getSessionRegistryStorage();
        $storage->expects($this->any())->method('getSessionInformation')->with('foobar')->will($this->returnValue($sessionInformation));
        $storage->expects($this->once())->method('setSessionInformation')->with($this->identicalTo($sessionInformation));
        $registry = $this->getSessionRegistry($storage);
        $registry->expireNow('foobar');
    }

    public function testRegisterNewSession()
    {
        $storage = $this->getSessionRegistryStorage();
        $storage->expects($this->once())->method('setSessionInformation')->with($this->isInstanceOf('Symfony\Component\Security\Http\Session\SessionInformation'));
        $registry = $this->getSessionRegistry($storage);
        $registry->registerNewSession('foo', 'bar', new \DateTime());
    }

    public function testRemoveSessionInformation()
    {
        $storage = $this->getSessionRegistryStorage();
        $storage->expects($this->once())->method('removeSessionInformation')->with('foobar');
        $registry = $this->getSessionRegistry($storage);
        $registry->removeSessionInformation('foobar');
    }

    private function getSessionRegistryStorage()
    {
        return $this->getMock('Symfony\Component\Security\Http\Session\SessionRegistryStorageInterface');
    }

    private function getSessionInformation()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Http\Session\SessionInformation')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getSessionRegistry($storage)
    {
        return new SessionRegistry($storage, 'Symfony\Component\Security\Http\Session\SessionInformation');
    }
}
