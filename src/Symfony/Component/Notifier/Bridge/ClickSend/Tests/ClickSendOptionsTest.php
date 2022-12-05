<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\ClickSend\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\ClickSend\ClickSendOptions;

class ClickSendOptionsTest extends TestCase
{
    public function testClickSendOptions()
    {
        $clickSendOptions = (new ClickSendOptions())->setFrom('test_from')->setCountry('test_country')->setCustomString('test_custom_string')->setFromEmail('test_from_email')->setListId('test_list_id')->setRecipientId('test_recipient_id')->setSchedule(999)->setSource('test_source');

        self::assertSame([
            'from' => 'test_from',
            'country' => 'test_country',
            'custom_string' => 'test_custom_string',
            'from_email' => 'test_from_email',
            'list_id' => 'test_list_id',
            'schedule' => 999,
            'source' => 'test_source',
        ], $clickSendOptions->toArray());
    }
}
