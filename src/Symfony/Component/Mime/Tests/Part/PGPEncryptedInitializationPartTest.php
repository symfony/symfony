<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Part;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Part\PGPEncryptedInitializationPart;

#[CoversClass(PGPEncryptedInitializationPart::class)]
final class PGPEncryptedInitializationPartTest extends TestCase
{
    public function testPGPEncryptedInitializationPart()
    {
        $part = (new PGPEncryptedInitializationPart())->toString();
        $this->assertStringContainsString('Content-Type: application/pgp-encrypted', $part, 'Content-Type not found');
        $this->assertStringContainsString('Content-Disposition: attachment', $part, 'Content-Disposition not found');
        $this->assertStringContainsString("\r\n\r\nVersion: 1\r\n", $part, 'Version not found');
    }
}
