<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RemoteEvent\Tests\Event\Sms;

use PHPUnit\Framework\TestCase;
use Symfony\Component\RemoteEvent\Event\Sms\SmsEvent;

class SmsEventTest extends TestCase
{
    public function testPhone()
    {
        $event = new SmsEvent('name', 'id', []);
        $event->setRecipientPhone($phone = '0102030405');
        $this->assertSame($phone, $event->getRecipientPhone());
    }
}
