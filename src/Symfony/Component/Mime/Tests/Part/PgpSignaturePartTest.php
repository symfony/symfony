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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Part\PgpSignaturePart;

class PgpSignaturePartTestToStringGadget
{
    public static bool $fired = false;

    public function __toString(): string
    {
        self::$fired = true;

        return '';
    }
}

class PgpSignaturePartTest extends TestCase
{
    public function testPGPSignaturePart()
    {
        $part = (new PgpSignaturePart(''))->toString();
        $this->assertStringContainsString('Content-Type: application/pgp-signature', $part, 'Content-Type not found');
        $this->assertStringContainsString('name=OpenPGP_signature.asc', $part, 'name not found');
        $this->assertStringContainsString('Content-Disposition: attachment', $part, 'Content-Disposition not found');
        $this->assertStringContainsString('filename=OpenPGP_signature', $part, 'filename not found');
        $this->assertStringContainsString('Content-Description: OpenPGP digital signature', $part, 'Content-Description not found');
    }

    public function testSerialize()
    {
        $part = new PgpSignaturePart("-----BEGIN PGP SIGNATURE-----\r\n\r\nc2lnbmF0dXJl\r\n-----END PGP SIGNATURE-----\r\n");

        $this->assertSame($part->toString(), unserialize(serialize($part))->toString());
    }

    public function testUnserializeRejectsObjectInBody()
    {
        $data = (new PgpSignaturePart('signature'))->__serialize();
        $data['body'] = new PgpSignaturePartTestToStringGadget();
        $payload = \sprintf('O:%d:"%s":%d:{', \strlen(PgpSignaturePart::class), PgpSignaturePart::class, \count($data));
        foreach ($data as $key => $value) {
            $payload .= serialize($key).serialize($value);
        }
        PgpSignaturePartTestToStringGadget::$fired = false;

        try {
            unserialize($payload.'}');
            $this->fail('Expected BadMethodCallException.');
        } catch (\BadMethodCallException $e) {
        }

        $this->assertFalse(PgpSignaturePartTestToStringGadget::$fired, '__toString gadget must not fire during unserialize');
    }
}
