<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Part\Multipart;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Part\Multipart\PGPEncryptedPart;

final class PGPEncryptedPartTest extends TestCase
{
    public function testPGPEncryptedPart()
    {
        $part = (new PGPEncryptedPart())->toString();
        $this->assertStringContainsString('Content-Type: multipart/encrypted', $part, 'Content-Type not found.');
        $this->assertStringContainsString('protocol="application/pgp-encrypted"', $part, 'Protocol not found');
    }
}
