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
        $messageBirdOptions = (new MessageBirdOptions())
            ->type('test_type')
            ->scheduledDatetime('test_scheduled_datetime')
            ->createdDatetime('test_created_datetime')
            ->dataCoding('test_data_coding')
            ->gateway(999)
            ->groupIds(['test_group_ids'])
            ->mClass(888)
            ->reference('test_reference')
            ->reportUrl('test_report_url')
            ->shortenUrls(true)
            ->typeDetails('test_type_details')
            ->validity(777);

        self::assertSame([
            'type' => 'test_type',
            'scheduledDatetime' => 'test_scheduled_datetime',
            'createdDatetime' => 'test_created_datetime',
            'dataCoding' => 'test_data_coding',
            'gateway' => 999,
            'groupIds' => ['test_group_ids'],
            'mClass' => 888,
            'reference' => 'test_reference',
            'reportUrl' => 'test_report_url',
            'shortenUrls' => true,
            'typeDetails' => 'test_type_details',
            'validity' => 777,
        ], $messageBirdOptions->toArray());
    }
}
