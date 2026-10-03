<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Kmip\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\ChaCha20Poly1305EncryptionScheme;

final class ChaCha20Poly1305EncryptionSchemeTest extends TestCase
{
    public function testNameAndCryptographicParameters()
    {
        $scheme = new ChaCha20Poly1305EncryptionScheme();

        $this->assertSame('chacha20-poly1305', $scheme->name());
        $this->assertSame('42002b0100000010'
            .'42002805000000040000001e00000000',
            bin2hex((new \ReflectionMethod($scheme, 'cryptographicParameters'))->invoke($scheme, 12)));
    }
}
