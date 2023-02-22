<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Http\Authenticator\HttpBasicAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Tests\Authenticator\Fixtures\PasswordUpgraderProvider;

class HttpBasicAuthenticatorTest extends TestCase
{
    private $userProvider;
    private $hasherFactory;
    private $hasher;
    private $authenticator;

    protected function setUp(): void
    {
        $this->userProvider = new InMemoryUserProvider();
        $this->hasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $this->hasher = $this->createMock(PasswordHasherInterface::class);
        $this->hasherFactory
            ->expects($this->any())
            ->method('getPasswordHasher')
            ->willReturn($this->hasher);

        $this->authenticator = new HttpBasicAuthenticator('test', $this->userProvider);
    }

    public function testExtractCredentialsAndUserFromRequest()
    {
        $request = new Request([], [], [], [], [], [
            'PHP_AUTH_USER' => 'TheUsername',
            'PHP_AUTH_PW' => 'ThePassword',
        ]);

        $this->userProvider->createUser($user = new InMemoryUser('TheUsername', 'ThePassword'));

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

    public static function provideMissingHttpBasicServerParameters()
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
