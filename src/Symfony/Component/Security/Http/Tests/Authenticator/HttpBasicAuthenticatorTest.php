<?php

namespace Symfony\Component\Security\Http\Tests\Authenticator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\User;
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
        $this->userProvider = new InMemoryUserProvider();
        $this->encoderFactory = $this->createMock(EncoderFactoryInterface::class);
        $this->encoder = $this->createMock(PasswordEncoderInterface::class);
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

        $this->userProvider->createUser($user = new User('TheUsername', 'ThePassword'));

        $passport = $this->authenticator->authenticate($request);
        $this->assertEquals('ThePassword', $passport->getBadge(PasswordCredentials::class)->getPassword());

        $this->assertTrue($user->isEqualTo($passport->getUser()));
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

        $this->userProvider = new PasswordUpgraderProvider(['test' => ['password' => 's$cr$t']]);
        $authenticator = new HttpBasicAuthenticator('test', $this->userProvider);

        $passport = $authenticator->authenticate($request);
        $this->assertTrue($passport->hasBadge(PasswordUpgradeBadge::class));
        $badge = $passport->getBadge(PasswordUpgradeBadge::class);
        $this->assertEquals('ThePassword', $badge->getAndErasePlaintextPassword());
    }
}
