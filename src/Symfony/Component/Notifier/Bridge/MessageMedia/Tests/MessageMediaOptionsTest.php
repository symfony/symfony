<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MessageMedia\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\MessageMedia\MessageMediaOptions;

class MessageMediaOptionsTest extends TestCase
{
    public function testMessageMediaOptions()
    {
        $messageMediaOptions = (new MessageMediaOptions())->setFrom('test_from')->setMedia(['test_media'])->setCallbackUrl('test_callback_url')->setFormat('test_format')->setRecipientId('test_recipient')->setDeliveryReport(true)->setMessageExpiryTimestamp(999)->setMetadata(['test_metadata'])->setScheduled('test_scheduled')->setSubject('test_subject');

        self::assertSame([
            'from' => 'test_from',
            'media' => ['test_media'],
            'callback_url' => 'test_callback_url',
            'format' => 'test_format',
            'delivery_report' => true,
            'message_expiry_timestamp' => 999,
            'metadata' => ['test_metadata'],
            'scheduled' => 'test_scheduled',
            'subject' => 'test_subject',
        ], $messageMediaOptions->toArray());
    }
}
