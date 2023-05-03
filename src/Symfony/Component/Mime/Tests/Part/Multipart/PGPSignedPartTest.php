<?php

declare(strict_types=1);

namespace Symfony\Component\Mime\Tests\Part\Multipart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Part\Multipart\PGPSignedPart;
use Symfony\Component\Mime\Part\TextPart;

#[CoversClass(PGPSignedPart::class)]
final class PGPSignedPartTest extends TestCase
{
    public function testPGPSignedPart()
    {
        $part = (new PGPSignedPart(new TextPart('Test')))->toString();
        $this->assertStringContainsString('Content-Type: multipart/signed', $part, 'Content-Type not found');
        $this->assertStringContainsString('micalg=pgp-sha512', $part, 'micalg not found');
        $this->assertStringContainsString('protocol="application/pgp-signature"', $part, 'protocol not found');
    }
}
