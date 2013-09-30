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

use Symfony\Component\Security\Csrf\CsrfTokenGenerator;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CsrfTokenGeneratorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * A non alpha-numeric byte string
     * @var string
     */
    private static $bytes;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $random;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $storage;

    /**
     * @var CsrfTokenGenerator
     */
    private $generator;

    public static function setUpBeforeClass()
    {
        self::$bytes = base64_decode('aMf+Tct/RLn2WQ==');
    }

    protected function setUp()
    {
        $this->random = $this->getMock('Symfony\Component\Security\Core\Util\SecureRandomInterface');
        $this->storage = $this->getMock('Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface');
        $this->generator = new CsrfTokenGenerator($this->storage, $this->random);
    }

    protected function tearDown()
    {
        $this->random = null;
        $this->storage = null;
        $this->generator = null;
    }

    public function testGenerateNewToken()
    {
        $this->storage->expects($this->once())
            ->method('getToken')
            ->with('token_id', false)
            ->will($this->returnValue(false));

        $this->storage->expects($this->once())
            ->method('setToken')
            ->with('token_id', $this->anything())
            ->will($this->returnCallback(function ($tokenId, $token) use (&$storedToken) {
                $storedToken = $token;
            }));

        $this->random->expects($this->once())
            ->method('nextBytes')
            ->will($this->returnValue(self::$bytes));

        $token = $this->generator->generateCsrfToken('token_id');

        $this->assertSame($token, $storedToken);
        $this->assertTrue(ctype_print($token), 'is printable');
        $this->assertStringNotMatchesFormat('%S+%S', $token, 'is URI safe');
        $this->assertStringNotMatchesFormat('%S/%S', $token, 'is URI safe');
        $this->assertStringNotMatchesFormat('%S=%S', $token, 'is URI safe');
    }

    public function testUseExistingTokenIfAvailable()
    {
        $this->storage->expects($this->once())
            ->method('getToken')
            ->with('token_id', false)
            ->will($this->returnValue('TOKEN'));

        $this->storage->expects($this->never())
            ->method('setToken');

        $this->random->expects($this->never())
            ->method('nextBytes');

        $token = $this->generator->generateCsrfToken('token_id');

        $this->assertEquals('TOKEN', $token);
    }

    public function testMatchingTokenIsValid()
    {
        $this->storage->expects($this->once())
            ->method('hasToken')
            ->with('token_id')
            ->will($this->returnValue(true));

        $this->storage->expects($this->once())
            ->method('getToken')
            ->with('token_id')
            ->will($this->returnValue('TOKEN'));

        $this->assertTrue($this->generator->isCsrfTokenValid('token_id', 'TOKEN'));
    }

    public function testNonMatchingTokenIsNotValid()
    {
        $this->storage->expects($this->once())
            ->method('hasToken')
            ->with('token_id')
            ->will($this->returnValue(true));

        $this->storage->expects($this->once())
            ->method('getToken')
            ->with('token_id')
            ->will($this->returnValue('TOKEN'));

        $this->assertFalse($this->generator->isCsrfTokenValid('token_id', 'FOOBAR'));
    }

    public function testNonExistingTokenIsNotValid()
    {
        $this->storage->expects($this->once())
            ->method('hasToken')
            ->with('token_id')
            ->will($this->returnValue(false));

        $this->storage->expects($this->never())
            ->method('getToken');

        $this->assertFalse($this->generator->isCsrfTokenValid('token_id', 'FOOBAR'));
    }
}
