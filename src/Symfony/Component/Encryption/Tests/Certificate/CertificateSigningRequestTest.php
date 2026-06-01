<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Encryption\Tests\Certificate;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Certificate\CertificateSigningRequest;
use Symfony\Component\Encryption\Certificate\DistinguishedName;

final class CertificateSigningRequestTest extends TestCase
{
    public function testAccessors(): void
    {
        $subject = new DistinguishedName(['CN' => 'example.com']);
        $csr = new CertificateSigningRequest(
            $subject,
            "-----BEGIN PUBLIC KEY-----\nMII...\n-----END PUBLIC KEY-----\n",
            "-----BEGIN CERTIFICATE REQUEST-----\nMII...\n-----END CERTIFICATE REQUEST-----\n",
        );

        self::assertSame('example.com', $csr->subject()->commonName());
        self::assertStringContainsString('PUBLIC KEY', $csr->publicKeyPem());
        self::assertStringContainsString('CERTIFICATE REQUEST', $csr->pem());
    }
}
