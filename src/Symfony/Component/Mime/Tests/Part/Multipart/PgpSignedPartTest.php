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
use Symfony\Component\Mime\Part\Multipart\PgpSignedPart;
use Symfony\Component\Mime\Part\TextPart;

final class PgpSignedPartTest extends TestCase
{
    public function testPGPSignedPart()
    {
        $part = (new PgpSignedPart('SHA512', new TextPart('Test')))->toString();
        $this->assertStringContainsString('Content-Type: multipart/signed', $part, 'Content-Type not found');
        $this->assertStringContainsString('micalg=pgp-sha512', $part, 'micalg not found');
        $this->assertStringContainsString('protocol="application/pgp-signature"', $part, 'protocol not found');
    }
}
