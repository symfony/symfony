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

use Symfony\Component\Encryption\EncryptionInterface;
use Symfony\Component\Encryption\KeyInterface;
use Symfony\Component\Encryption\Sodium\SodiumEncryption;
use Symfony\Component\Encryption\Sodium\SodiumKey;
use Symfony\Component\Encryption\Tests\AbstractEncryptionTest;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class SodiumEncryptionTest extends AbstractEncryptionTest
{
    protected function getEncryption(): EncryptionInterface
    {
        if (!\function_exists('sodium_crypto_box_keypair')) {
            $this->markTestSkipped('Sodium extension is not installed and enabled.');
        }

        return new SodiumEncryption();
    }

    protected function createPrivateKey(KeyInterface $key): KeyInterface
    {
        return SodiumKey::fromPrivateKey($key->getPrivateKey());
    }
}
