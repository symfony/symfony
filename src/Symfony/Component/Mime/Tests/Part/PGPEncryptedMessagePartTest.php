<?php

namespace Tests\Part;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Part\PGPEncryptedMessagePart;

#[CoversClass(PGPEncryptedMessagePart::class)]
final class PGPEncryptedMessagePartTest extends TestCase
{
    public function testPGPEncryptedMessagePart()
    {
        $part = (new PGPEncryptedMessagePart(''))->toString();
        $this->assertStringContainsString('Content-Type: application/octet-stream', $part, 'Content-Type not found');
        $this->assertStringContainsString('Content-Disposition: inline', $part, 'Content-Disposition not found');
        $this->assertStringContainsString('filename=msg.asc', $part, 'filename not found');
    }
}
