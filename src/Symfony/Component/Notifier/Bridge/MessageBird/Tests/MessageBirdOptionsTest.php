<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MessageBird\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\MessageBird\MessageBirdOptions;

class MessageBirdOptionsTest extends TestCase
{
    public function testMessageBirdOptions()
    {
        $messageBirdOptions = (new MessageBirdOptions())->setFrom('test_from')->setType('test_type')->setScheduledDatetime('test_scheduled_datetime')->setCreatedDatetime('test_created_datetime')->setRecipientId('test_recipient')->setDataCoding('test_data_coding')->setGateway(999)->setGroupIds(['test_group_ids'])->setMClass(888)->setReference('test_reference')->setReportUrl('test_report_url')->setShortenUrls(true)->setTypeDetails('test_type_details')->setValidity(777);

        self::assertSame([
            'from' => 'test_from',
            'type' => 'test_type',
            'scheduled_datetime' => 'test_scheduled_datetime',
            'created_datetime' => 'test_created_datetime',
            'data_coding' => 'test_data_coding',
            'gateway' => 999,
            'group_ids' => ['test_group_ids'],
            'm_class' => 888,
            'reference' => 'test_reference',
            'report_url' => 'test_report_url',
            'shorten_urls' => true,
            'type_details' => 'test_type_details',
            'validity' => 777,
        ], $messageBirdOptions->toArray());
    }
}
