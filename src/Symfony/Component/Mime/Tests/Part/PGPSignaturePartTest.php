<?php

namespace Symfony\Component\Mime\Tests\Part;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Part\PGPSignaturePart;

#[CoversClass(PGPSignaturePart::class)]
final class PGPSignaturePartTest extends TestCase
{
    public function testPGPSignaturePart()
    {
        $part = (new PGPSignaturePart(''))->toString();
        $this->assertStringContainsString('Content-Type: application/pgp-signature', $part, 'Content-Type not found');
        $this->assertStringContainsString('name=OpenPGP_signature.asc', $part, 'name not found');
        $this->assertStringContainsString('Content-Disposition: attachment', $part, 'Content-Disposition not found');
        $this->assertStringContainsString('filename=OpenPGP_signature', $part, 'filename not found');
        $this->assertStringContainsString('Content-Description: OpenPGP digital signature', $part, 'Content-Description not found');
        $this->assertStringContainsString('MIME-Version: 1.0', $part, 'MIME-Version not found');
    }
}
