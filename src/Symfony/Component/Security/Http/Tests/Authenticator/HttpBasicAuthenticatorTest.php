<?php

namespace Symfony\Component\Security\Http\Tests\Authenticator;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\HttpBasicAuthenticator;

class HttpBasicAuthenticatorTest extends TestCase
{
    private $userProvider;
    private $encoderFactory;
    private $encoder;
    private $authenticator;

    protected function setUp(): void
    {
        $this->userProvider = $this->getMockBuilder(UserProviderInterface::class)->getMock();
        $this->encoderFactory = $this->getMockBuilder(EncoderFactoryInterface::class)->getMock();
        $this->encoder = $this->getMockBuilder(PasswordEncoderInterface::class)->getMock();
        $this->encoderFactory
            ->expects($this->any())
            ->method('getEncoder')
            ->willReturn($this->encoder);

        $this->authenticator = new HttpBasicAuthenticator('test', $this->userProvider);
    }

    public function testExtractCredentialsAndUserFromRequest()
    {
        $request = new Request([], [], [], [], [], [
            'PHP_AUTH_USER' => 'TheUsername',
            'PHP_AUTH_PW' => 'ThePassword',
        ]);

        $credentials = $this->authenticator->getCredentials($request);
        $this->assertEquals([
            'username' => 'TheUsername',
            'password' => 'ThePassword',
        ], $credentials);

        $mockedUser = $this->getMockBuilder(UserInterface::class)->getMock();
        $mockedUser->expects($this->any())->method('getPassword')->willReturn('ThePassword');

        $this->userProvider
            ->expects($this->any())
            ->method('loadUserByUsername')
            ->with('TheUsername')
            ->willReturn($mockedUser);

        $user = $this->authenticator->getUser($credentials);
        $this->assertSame($mockedUser, $user);

        $this->assertEquals('ThePassword', $this->authenticator->getPassword($credentials));
    }

    /**
     * @dataProvider provideMissingHttpBasicServerParameters
     */
    public function testHttpBasicServerParametersMissing(array $serverParameters)
    {
        $request = new Request([], [], [], [], [], $serverParameters);

        $this->assertFalse($this->authenticator->supports($request));
    }

    public function provideMissingHttpBasicServerParameters()
    {
        return [
            [[]],
            [['PHP_AUTH_PW' => 'ThePassword']],
        ];
    }
}
