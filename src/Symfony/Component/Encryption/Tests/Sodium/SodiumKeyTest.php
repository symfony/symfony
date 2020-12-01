<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Tests\Encryption\Sodium;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Sodium\SodiumKey;

class SodiumKeyTest extends TestCase
{
    public function testKeySize()
    {
        // Secret is longer than \SODIUM_CRYPTO_SECRETBOX_KEYBYTES
        $secret = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $keyA = SodiumKey::fromSecret($secret.'v1');
        $keyB = SodiumKey::fromSecret($secret.'v2');

        $this->assertNotEquals($keyA->getSecret(), $keyB->getSecret());
        $this->assertTrue(\SODIUM_CRYPTO_SECRETBOX_KEYBYTES === \strlen($keyA->getSecret()));
        $this->assertTrue(\SODIUM_CRYPTO_SECRETBOX_KEYBYTES === \strlen($keyB->getSecret()));
    }
}
