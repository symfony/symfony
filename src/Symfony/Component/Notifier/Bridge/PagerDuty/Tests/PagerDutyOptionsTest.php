<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\PagerDuty\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\PagerDuty\PagerDutyOptions;

class PagerDutyOptionsTest extends TestCase
{
    public function testPayloadOptionsAreKept()
    {
        $options = new PagerDutyOptions('routing-key', 'trigger', 'error', [
            'payload' => [
                'timestamp' => '2015-07-17T08:42:58.315+00:00',
                'component' => 'postgres',
                'group' => 'prod-datapipe',
                'class' => 'deploy',
                'custom_details' => ['ping time' => '1500ms'],
            ],
        ]);

        $this->assertSame([
            'payload' => [
                'severity' => 'error',
                'timestamp' => '2015-07-17T08:42:58.315+00:00',
                'component' => 'postgres',
                'group' => 'prod-datapipe',
                'class' => 'deploy',
                'custom_details' => ['ping time' => '1500ms'],
            ],
            'routing_key' => 'routing-key',
            'event_action' => 'trigger',
        ], $options->toArray());
    }

    public function testTimestampIsNormalizedToRfc3339Extended()
    {
        $options = new PagerDutyOptions('routing-key', 'trigger', 'info', ['payload' => ['timestamp' => '2015-07-17T08:42:58.315UTC']]);

        $this->assertSame('2015-07-17T08:42:58.315+00:00', $options->toArray()['payload']['timestamp']);
    }

    public function testAttachCustomDetails()
    {
        $options = (new PagerDutyOptions('routing-key', 'trigger', 'info'))
            ->attachCustomDetails(['ping time' => '1500ms'])
            ->attachCustomDetails(['load avg' => 0.75]);

        $this->assertSame(['ping time' => '1500ms', 'load avg' => 0.75], $options->toArray()['payload']['custom_details']);
    }
}
