<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\PrivacyPass;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\PrivacyPass\EmptyRedemptionContext;

class EmptyRedemptionContextTest extends TestCase
{
    public function testItBindsTheTokensToNothing()
    {
        $this->assertSame([''], (new EmptyRedemptionContext())->getAcceptedContexts(new Request()));
    }

    public function testItsChallengesNeverStopBeingAccepted()
    {
        $this->assertNull((new EmptyRedemptionContext())->getLifetime());
    }
}
