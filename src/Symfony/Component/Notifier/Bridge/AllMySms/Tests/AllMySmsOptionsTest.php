<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\AllMySms\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\AllMySms\AllMySmsOptions;

class AllMySmsOptionsTest extends TestCase
{
    public function testAllMySmsOptions()
    {
        $allMySmsOptions = (new AllMySmsOptions())->setFrom('test_from')->setAlerting(1)->setDate('test_date')->setCampaignName('test_campaign_name')->setRecipientId('test_recipient')->setCliMsgId('test_cli_msg_id')->setSimulate(1)->setUniqueIdentifier('test_unique_identifier')->setVerbose(1);

        self::assertSame([
            'from' => 'test_from',
            'alerting' => 1,
            'date' => 'test_date',
            'campaign_name' => 'test_campaign_name',
            'cli_msg_id' => 'test_cli_msg_id',
            'simulate' => 1,
            'unique_identifier' => 'test_unique_identifier',
            'verbose' => 1,
        ], $allMySmsOptions->toArray());
    }
}
