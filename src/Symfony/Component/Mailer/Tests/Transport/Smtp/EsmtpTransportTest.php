<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Transport\Smtp;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class EsmtpTransportTest extends TestCase
{
    public function testToString()
    {
        $t = new EsmtpTransport();
        $this->assertEquals('smtp://localhost', (string) $t);

        $t = new EsmtpTransport('example.com');
        if (\defined('OPENSSL_VERSION_NUMBER')) {
            $this->assertEquals('smtps://example.com', (string) $t);
        } else {
            $this->assertEquals('smtp://example.com', (string) $t);
        }

        $t = new EsmtpTransport('example.com', 2525);
        $this->assertEquals('smtp://example.com:2525', (string) $t);

        $t = new EsmtpTransport('example.com', 0, true);
        $this->assertEquals('smtps://example.com', (string) $t);

        $t = new EsmtpTransport('example.com', 0, false);
        $this->assertEquals('smtp://example.com', (string) $t);

        $t = new EsmtpTransport('example.com', 466, true);
        $this->assertEquals('smtps://example.com:466', (string) $t);
    }
}
