<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Ciphertext;

class CiphertextTest extends TestCase
{
    public function testStringableReturnsBlob()
    {
        $ciphertext = new Ciphertext('raw-bytes', 'app');

        $this->assertSame('raw-bytes', (string) $ciphertext);
        $this->assertSame('app', $ciphertext->keyId);
    }
}
