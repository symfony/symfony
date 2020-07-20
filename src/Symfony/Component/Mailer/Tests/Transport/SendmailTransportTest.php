<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Transport\SendmailTransport;

class SendmailTransportTest extends TestCase
{
    public function testToString()
    {
        $t = new SendmailTransport();
        $this->assertEquals('smtp://sendmail', (string) $t);
    }
}
