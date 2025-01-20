<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authorization;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UserAuthorizationCheckerToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\UserAuthorizationChecker;
use Symfony\Component\Security\Core\User\InMemoryUser;

class UserAuthorizationCheckerTest extends TestCase
{
    private AccessDecisionManagerInterface&MockObject $accessDecisionManager;
    private UserAuthorizationChecker $authorizationChecker;

    protected function setUp(): void
    {
        $this->accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);

        $this->authorizationChecker = new UserAuthorizationChecker($this->accessDecisionManager);
    }

    /**
     * @dataProvider isGrantedProvider
     */
    public function testIsGranted(bool $decide, array $roles)
    {
        $user = new InMemoryUser('username', 'password', $roles);

        $this->accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->with($this->callback(fn (UserAuthorizationCheckerToken $token): bool => $user === $token->getUser()), $this->identicalTo(['ROLE_FOO']))
            ->willReturn($decide);

        $this->assertSame($decide, $this->authorizationChecker->isGrantedForUser($user, 'ROLE_FOO'));
    }

    public static function isGrantedProvider(): array
    {
        return [
            [false, ['ROLE_USER']],
            [true, ['ROLE_USER', 'ROLE_FOO']],
        ];
    }

    public function testIsGrantedWithObjectAttribute()
    {
        $attribute = new \stdClass();

        $token = new UserAuthorizationCheckerToken(new InMemoryUser('username', 'password', ['ROLE_USER']));

        $this->accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->with($this->isInstanceOf($token::class), $this->identicalTo([$attribute]))
            ->willReturn(true);
        $this->assertTrue($this->authorizationChecker->isGrantedForUser($token->getUser(), $attribute));
    }
}
