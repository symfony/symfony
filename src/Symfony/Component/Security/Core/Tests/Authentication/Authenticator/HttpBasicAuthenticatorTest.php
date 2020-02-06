<?php

namespace Symfony\Component\Security\Core\Tests\Authentication\Authenticator;

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
    /** @var UserProviderInterface|MockObject */
    private $userProvider;
    /** @var EncoderFactoryInterface|MockObject */
    private $encoderFactory;
    /** @var PasswordEncoderInterface|MockObject */
    private $encoder;

    protected function setUp(): void
    {
        $this->userProvider = $this->getMockBuilder(UserProviderInterface::class)->getMock();
        $this->encoderFactory = $this->getMockBuilder(EncoderFactoryInterface::class)->getMock();
        $this->encoder = $this->getMockBuilder(PasswordEncoderInterface::class)->getMock();
        $this->encoderFactory
            ->expects($this->any())
            ->method('getEncoder')
            ->willReturn($this->encoder);
    }

    public function testValidUsernameAndPasswordServerParameters()
    {
        $request = new Request([], [], [], [], [], [
            'PHP_AUTH_USER' => 'TheUsername',
            'PHP_AUTH_PW' => 'ThePassword',
        ]);

        $authenticator = new HttpBasicAuthenticator('test', $this->userProvider, $this->encoderFactory);
        $credentials = $authenticator->getCredentials($request);
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

        $user = $authenticator->getUser($credentials, $this->userProvider);
        $this->assertSame($mockedUser, $user);

        $this->encoder
            ->expects($this->any())
            ->method('isPasswordValid')
            ->with('ThePassword', 'ThePassword', null)
            ->willReturn(true);

        $checkCredentials = $authenticator->checkCredentials($credentials, $user);
        $this->assertTrue($checkCredentials);
    }

    /** @dataProvider provideInvalidPasswords */
    public function testInvalidPassword($presentedPassword, $exceptionMessage)
    {
        $authenticator = new HttpBasicAuthenticator('test', $this->userProvider, $this->encoderFactory);

        $this->encoder
            ->expects($this->any())
            ->method('isPasswordValid')
            ->willReturn(false);

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $authenticator->checkCredentials([
            'username' => 'TheUsername',
            'password' => $presentedPassword,
        ], $this->getMockBuilder(UserInterface::class)->getMock());
    }

    public function provideInvalidPasswords()
    {
        return [
            ['InvalidPassword', 'The presented password is invalid.'],
            ['', 'The presented password cannot be empty.'],
        ];
    }

    /** @dataProvider provideMissingHttpBasicServerParameters */
    public function testHttpBasicServerParametersMissing(array $serverParameters)
    {
        $request = new Request([], [], [], [], [], $serverParameters);

        $authenticator = new HttpBasicAuthenticator('test', $this->userProvider, $this->encoderFactory);
        $this->assertFalse($authenticator->supports($request));
    }

    public function provideMissingHttpBasicServerParameters()
    {
        return [
            [[]],
            [['PHP_AUTH_PW' => 'ThePassword']],
        ];
    }
}
