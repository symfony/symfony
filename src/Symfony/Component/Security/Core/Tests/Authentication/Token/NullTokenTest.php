<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication\Token;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\AuthenticationMethod;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;

class NullTokenTest extends TestCase
{
    public function testItHoldsNoAuthenticationProof()
    {
        $this->assertSame([], (new NullToken())->getAuthenticationProofs());
    }

    public function testAuthenticationProofsCannotBeRecordedOnIt()
    {
        $this->expectException(\BadMethodCallException::class);

        (new NullToken())->setAuthenticationProofs([AuthenticationMethod::PASSWORD => time()]);
    }
}
