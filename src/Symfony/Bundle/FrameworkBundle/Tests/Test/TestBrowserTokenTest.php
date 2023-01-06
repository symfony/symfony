<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Test;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\TestBrowserToken;

final class TestBrowserTokenTest extends TestCase
{
    public function testCanBeSerializedAndUnserialized()
    {
        $token = unserialize(serialize(new TestBrowserToken()));

        $this->assertSame('main', $token->getFirewallName());
    }
}
