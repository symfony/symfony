<?php

namespace Symfony\Component\Security\Http\Tests\Authenticator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\HttpBasicAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Tests\Authenticator\Fixtures\PasswordUpgraderProvider;

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

        $this->userProvider
            ->expects($this->any())
            ->method('loadUserByUsername')
            ->with('TheUsername')
            ->willReturn($user = new User('TheUsername', 'ThePassword'));

        $passport = $this->authenticator->authenticate($request);
        $this->assertEquals('ThePassword', $passport->getBadge(PasswordCredentials::class)->getPassword());

        $this->assertSame($user, $passport->getUser());
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

    public function testUpgradePassword()
    {
        $request = new Request([], [], [], [], [], [
            'PHP_AUTH_USER' => 'TheUsername',
            'PHP_AUTH_PW' => 'ThePassword',
        ]);

        $this->userProvider = $this->createMock(PasswordUpgraderProvider::class);
        $this->userProvider->expects($this->any())->method('loadUserByUsername')->willReturn(new User('test', 's$cr$t'));
        $authenticator = new HttpBasicAuthenticator('test', $this->userProvider);

        $passport = $authenticator->authenticate($request);
        $this->assertTrue($passport->hasBadge(PasswordUpgradeBadge::class));
        $badge = $passport->getBadge(PasswordUpgradeBadge::class);
        $this->assertEquals('ThePassword', $badge->getAndErasePlaintextPassword());
    }
}
