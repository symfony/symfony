<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Cloudflare\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Cloudflare\Transport\CloudflareSmtpTransport;

/**
 * @author vadage
 */
final class CloudflareSmtpTransportTest extends TestCase
{
    #[DataProvider('getTransportData')]
    public function testToString(CloudflareSmtpTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): array
    {
        return [
            [
                new CloudflareSmtpTransport('API_TOKEN'),
                'smtps://smtp.mx.cloudflare.net',
            ],
        ];
    }

    public function testCredentials()
    {
        $transport = new CloudflareSmtpTransport('API_TOKEN');

        // Cloudflare requires literal value `api_token` as username.
        $this->assertSame('api_token', $transport->getUsername());
        $this->assertSame('API_TOKEN', $transport->getPassword());
    }
}
