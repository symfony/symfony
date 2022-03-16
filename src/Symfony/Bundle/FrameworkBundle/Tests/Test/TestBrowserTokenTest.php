<?php

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
