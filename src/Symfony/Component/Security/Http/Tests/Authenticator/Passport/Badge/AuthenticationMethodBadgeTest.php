<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator\Passport\Badge;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\AuthenticationMethod;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\AuthenticationMethodBadge;

class AuthenticationMethodBadgeTest extends TestCase
{
    public function testItListsEachMethodOnce()
    {
        $badge = new AuthenticationMethodBadge(AuthenticationMethod::PASSWORD, AuthenticationMethod::ONE_TIME_PASSWORD, AuthenticationMethod::PASSWORD);

        $this->assertSame([AuthenticationMethod::PASSWORD, AuthenticationMethod::ONE_TIME_PASSWORD], $badge->methods);
        $this->assertTrue($badge->isResolved());
    }

    public function testItRequiresAMethod()
    {
        $this->expectException(\InvalidArgumentException::class);

        new AuthenticationMethodBadge();
    }
}
