<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Tests\Component\Security\Core\Authentication\RememberMe;

use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentToken;
use Symfony\Component\Security\Core\Authentication\RememberMe\InMemoryTokenProvider;

class InMemoryTokenProviderTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateNewToken()
    {
        $provider = new InMemoryTokenProvider();

        $token = new PersistentToken('foo', 'foo', 'foo', 'foo', new \DateTime());
        $provider->createNewToken($token);

        $this->assertSame($provider->loadTokenBySeries('foo'), $token);
    }

    /**
     * @expectedException Symfony\Component\Security\Core\Exception\TokenNotFoundException
     */
    public function testLoadTokenBySeriesThrowsNotFoundException()
    {
        $provider = new InMemoryTokenProvider();
        $provider->loadTokenBySeries('foo');
    }

    public function testUpdateToken()
    {
        $provider = new InMemoryTokenProvider();

        $token = new PersistentToken('foo', 'foo', 'foo', 'foo', new \DateTime());
        $provider->createNewToken($token);
        $provider->updateToken('foo', 'newFoo', $lastUsed = new \DateTime());
        $token = $provider->loadTokenBySeries('foo');

        $this->assertEquals('newFoo', $token->getTokenValue());
        $this->assertSame($token->getLastUsed(), $lastUsed);
    }

    /**
     * @expectedException Symfony\Component\Security\Core\Exception\TokenNotFoundException
     */
    public function testDeleteToken()
    {
        $provider = new InMemoryTokenProvider();

        $token = new PersistentToken('foo', 'foo', 'foo', 'foo', new \DateTime());
        $provider->createNewToken($token);
        $provider->deleteTokenBySeries('foo');
        $provider->loadTokenBySeries('foo');
    }
}
