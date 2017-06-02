<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Csrf\CsrfProvider;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\SessionCsrfProvider;

/**
 * @group legacy
 */
class LegacySessionCsrfProviderTest extends TestCase
{
    protected $provider;
    protected $session;

    protected function setUp()
    {
        $this->session = $this->getMockBuilder('Symfony\Component\HttpFoundation\Session\Session')->disableOriginalConstructor()->getMock();
        $this->provider = new SessionCsrfProvider($this->session, 'SECRET');
    }

    protected function tearDown()
    {
        $this->provider = null;
        $this->session = null;
    }

    public function testGenerateCsrfToken()
    {
        $this->session->expects($this->once())
            ->method('getId')
            ->will($this->returnValue('ABCDEF'));

        $token = $this->provider->generateCsrfToken('foo');

        $this->assertEquals(sha1('SECRET'.'foo'.'ABCDEF'), $token);
    }

    public function testIsCsrfTokenValidSucceeds()
    {
        $this->session->expects($this->once())
            ->method('getId')
            ->will($this->returnValue('ABCDEF'));

        $token = sha1('SECRET'.'foo'.'ABCDEF');

        $this->assertTrue($this->provider->isCsrfTokenValid('foo', $token));
    }

    public function testIsCsrfTokenValidFails()
    {
        $this->session->expects($this->once())
            ->method('getId')
            ->will($this->returnValue('ABCDEF'));

        $token = sha1('SECRET'.'bar'.'ABCDEF');

        $this->assertFalse($this->provider->isCsrfTokenValid('foo', $token));
    }
}
