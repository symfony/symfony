<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Csrf\CsrfProvider;

use Symfony\Component\Form\Extension\Csrf\CsrfProvider\DefaultCsrfProvider;

class DefaultCsrfProviderTest extends \PHPUnit_Framework_TestCase
{
    protected $provider;

    public static function setUpBeforeClass()
    {
        @session_start();
    }

    protected function setUp()
    {
        $this->provider = new DefaultCsrfProvider('SECRET');
    }

    protected function tearDown()
    {
        $this->provider = null;
    }

    public function testGenerateCsrfToken()
    {
        $token = $this->provider->generateCsrfToken('foo');

        $this->assertEquals(sha1('SECRET'.'foo'.session_id()), $token);
    }

    public function testIsCsrfTokenValidSucceeds()
    {
        $token = sha1('SECRET'.'foo'.session_id());

        $this->assertTrue($this->provider->isCsrfTokenValid('foo', $token));
    }

    public function testIsCsrfTokenValidFails()
    {
        $token = sha1('SECRET'.'bar'.session_id());

        $this->assertFalse($this->provider->isCsrfTokenValid('foo', $token));
    }
}
