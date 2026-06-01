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

namespace Symfony\Component\Encryption\Certificate;

/**
 * A PKCS#10 Certificate Signing Request.
 *
 * Obtain instances from {@see \Symfony\Component\Encryption\CertificateManager::createCsr()}.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
final class CertificateSigningRequest
{
    public function __construct(
        private readonly DistinguishedName $subject,
        private readonly string $publicKeyPem,
        private readonly string $pem,
    ) {
    }

    public function subject(): DistinguishedName
    {
        return $this->subject;
    }

    public function publicKeyPem(): string
    {
        return $this->publicKeyPem;
    }

    public function pem(): string
    {
        return $this->pem;
    }
}
