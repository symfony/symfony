<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\Tests\Authentication\RememberMe;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Security\Core\Authentication\RememberMe\PersistentToken;

class PersistentTokenTest extends TestCase
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
