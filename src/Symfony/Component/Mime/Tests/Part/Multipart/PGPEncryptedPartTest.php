<?php

declare(strict_types=1);

namespace Symfony\Component\Mime\Tests\Part\Multipart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Part\Multipart\PGPEncryptedPart;

#[CoversClass(PGPEncryptedPart::class)]
final class PGPEncryptedPartTest extends TestCase
{
    public function testPGPEncryptedPart()
    {
        $part = (new PGPEncryptedPart())->toString();
        $this->assertStringContainsString('Content-Type: multipart/encrypted', $part, 'Content-Type not found.');
        $this->assertStringContainsString('protocol="application/pgp-encrypted"', $part, 'Protocol not found');
    }
}
