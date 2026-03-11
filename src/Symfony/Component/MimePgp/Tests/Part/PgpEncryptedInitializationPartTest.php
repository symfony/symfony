<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\MimePgp\Tests\Part;

use PHPUnit\Framework\TestCase;
use Symfony\Component\MimePgp\Mime\Part\PgpEncryptedInitializationPart;

class PgpEncryptedInitializationPartTest extends TestCase
{
    public function testPGPEncryptedInitializationPart()
    {
        $part = (new PgpEncryptedInitializationPart())->toString();
        $this->assertStringContainsString('Content-Type: application/pgp-encrypted', $part, 'Content-Type not found');
        $this->assertStringContainsString('Content-Disposition: attachment', $part, 'Content-Disposition not found');
        $this->assertStringContainsString("\r\n\r\nVersion: 1\r\n", $part, 'Version not found');
    }
}
