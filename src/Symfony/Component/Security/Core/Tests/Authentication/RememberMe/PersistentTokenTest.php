<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication\RememberMe;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentToken;

class PersistentTokenTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testConstructor()
    {
        $lastUsed = new \DateTime();
        $token = new PersistentToken('fooclass', 'fooname', 'fooseries', 'footokenvalue', $lastUsed);

        self::assertEquals('fooclass', $token->getClass());
        self::assertEquals('fooname', $token->getUserIdentifier());
        self::assertEquals('fooseries', $token->getSeries());
        self::assertEquals('footokenvalue', $token->getTokenValue());
        self::assertSame($lastUsed, $token->getLastUsed());
    }

    /**
     * @group legacy
     */
    public function testLegacyGetUsername()
    {
        $token = new PersistentToken('fooclass', 'fooname', 'fooseries', 'footokenvalue', new \DateTime());

        $this->expectDeprecation('Since symfony/security-core 5.3: Method "Symfony\Component\Security\Core\Authentication\RememberMe\PersistentToken::getUsername()" is deprecated, use getUserIdentifier() instead.');
        self::assertEquals('fooname', $token->getUsername());
    }
}
