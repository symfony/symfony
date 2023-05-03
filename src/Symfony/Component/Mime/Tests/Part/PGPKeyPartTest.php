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
use Symfony\Component\Mime\Part\PGPKeyPart;

#[CoversClass(PGPKeyPart::class)]
final class PGPKeyPartTest extends TestCase
{
    public function testPGPKeyPartWithStandardKeyName()
    {
        $part = (new PGPKeyPart(''))->toString();
        $this->assertStringContainsString('Content-Type: application/pgp-key', $part, 'Content-Type not found');
        $this->assertStringContainsString('Content-Disposition: attachment', $part, 'Content-Disposition not found');
        $this->assertStringContainsString('filename=public-key.asc', $part, 'filename not found');
        $this->assertStringContainsString('Content-Transfer-Encoding: base64', $part, 'Content-Transfer-Encoding not found');
        $this->assertStringContainsString('MIME-Version: 1.0', $part, 'MIME-Version not found');
    }

    public function testPGPKeyPartWithCustomKeyName()
    {
        $part = (new PGPKeyPart('', 'custom.asc'))->toString();
        $this->assertStringContainsString('Content-Type: application/pgp-key', $part, 'Content-Type not found');
        $this->assertStringContainsString('Content-Disposition: attachment', $part, 'Content-Disposition not found');
        $this->assertStringContainsString('filename=custom.asc', $part, 'filename not found');
        $this->assertStringContainsString('Content-Transfer-Encoding: base64', $part, 'Content-Transfer-Encoding not found');
        $this->assertStringContainsString('MIME-Version: 1.0', $part, 'MIME-Version not found');
    }
}
