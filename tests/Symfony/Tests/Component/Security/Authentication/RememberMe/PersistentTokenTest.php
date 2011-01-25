<?php

namespace Symfony\Tests\Component\Security\Authentication\RememberMe;

use Symfony\Component\Security\Authentication\RememberMe\PersistentToken;

class PersistentTokenTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $lastUsed = new \DateTime();
        $token = new PersistentToken('fooclass', 'fooname', 'fooseries', 'footokenvalue', $lastUsed);

        $this->assertEquals('fooclass', $token->getClass());
        $this->assertEquals('fooname', $token->getUsername());
        $this->assertEquals('fooseries', $token->getSeries());
        $this->assertEquals('footokenvalue', $token->getTokenValue());
        $this->assertSame($lastUsed, $token->getLastUsed());
    }
}