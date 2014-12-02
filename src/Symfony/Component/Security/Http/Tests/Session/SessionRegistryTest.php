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
    public function testGetAllSessionsInformation()
    {
        $storage = $this->getSessionRegistryStorage();
        $storage->expects($this->once())->method('getAllSessionsInformation')->with('foo', true);
        $registry = $this->getSessionRegistry($storage);
        $registry->getAllSessionsInformation('foo', true);
    }

    public function testGetSessionInformation()
    {
        $storage = $this->getSessionRegistryStorage();
        $storage->expects($this->once())->method('getSessionInformation')->with('foobar');
        $registry = $this->getSessionRegistry($storage);
        $registry->getSessionInformation('foobar');
    }

    public function testUpdateLastUsed()
    {
        $sessionInformation = $this->getSessionInformation();
        $sessionInformation->expects($this->once())->method('updateLastUsed');
        $storage = $this->getSessionRegistryStorage();
        $storage->expects($this->any())->method('getSessionInformation')->with('foobar')->will($this->returnValue($sessionInformation));
        $storage->expects($this->once())->method('setSessionInformation')->with($sessionInformation);
        $registry = $this->getSessionRegistry($storage);
        $registry->updateLastUsed('foobar', time() - 5);
    }

    public function testExpireAt()
    {
        $sessionInformation = $this->getSessionInformation();
        $sessionInformation->expects($this->once())->method('expireAt');
        $storage = $this->getSessionRegistryStorage();
        $storage->expects($this->any())->method('getSessionInformation')->with('foobar')->will($this->returnValue($sessionInformation));
        $storage->expects($this->once())->method('setSessionInformation')->with($this->identicalTo($sessionInformation));
        $registry = $this->getSessionRegistry($storage);
        $registry->expireAt('foobar', time() - 5);
    }

    public function testExpireNow()
    {
        $sessionInformation = $this->getSessionInformation();
        $sessionInformation->expects($this->once())->method('expireAt');
        $storage = $this->getSessionRegistryStorage();
        $storage->expects($this->any())->method('getSessionInformation')->with('foobar')->will($this->returnValue($sessionInformation));
        $storage->expects($this->once())->method('setSessionInformation')->with($this->identicalTo($sessionInformation));
        $registry = $this->getSessionRegistry($storage);
        $registry->expireNow('foobar');
    }

    public function testRegisterNewSessionInformation()
    {
        $storage = $this->getSessionRegistryStorage();
        $storage->expects($this->once())->method('setSessionInformation')->with($this->isInstanceOf('Symfony\Component\Security\Http\Session\SessionInformation'));
        $registry = $this->getSessionRegistry($storage);
        $registry->registerNewSessionInformation('foo', 'bar', time());
    }

    public function testRegisterNewSession()
    {
        $metadata = $this->getMock('Symfony\Component\HttpFoundation\Session\Storage\MetadataBag');
        $metadata->expects($this->once())->method('getLastUsed')->will($this->returnValue(time() - 5));
        $session = $this->getMock('Symfony\Component\HttpFoundation\Session\SessionInterface');
        $session->expects($this->any())->method('getId')->will($this->returnValue('foobar'));
        $session->expects($this->any())->method('getMetadataBag')->will($this->returnValue($metadata));
        $storage = $this->getSessionRegistryStorage();
        $storage->expects($this->once())->method('setSessionInformation')->with($this->isInstanceOf('Symfony\Component\Security\Http\Session\SessionInformation'));
        $registry = $this->getSessionRegistry($storage);
        $registry->registerNewSession('foo', $session);
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
