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
        $allMySmsOptions = (new AllMySmsOptions())
            ->alerting(1)
            ->date('test_date')
            ->campaignName('test_campaign_name')
            ->cliMsgId('test_cli_msg_id')
            ->simulate(1)
            ->uniqueIdentifier('test_unique_identifier')
            ->verbose(1);

        self::assertSame([
            'alerting' => 1,
            'date' => 'test_date',
            'campaignName' => 'test_campaign_name',
            'cliMsgId' => 'test_cli_msg_id',
            'simulate' => 1,
            'uniqueIdentifier' => 'test_unique_identifier',
            'verbose' => 1,
        ], $allMySmsOptions->toArray());
    }
}
