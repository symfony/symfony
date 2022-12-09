<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Bandwidth\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Bandwidth\BandwidthOptions;

class BandwidthOptionsTest extends TestCase
{
    public function testBandwidthOptions()
    {
        $bandwidthOptions = (new BandwidthOptions())->setFrom('test_from')->setRecipientId('test_recipient')->setMedia(['foo'])->setTo(['test_too'])->setTag('test_tag')->setAccountId('test_account_id')->setApplicationId('test_application_id')->setExpiration('test_expiration')->setPriority('test_priority');

        self::assertSame([
            'from' => 'test_from',
            'media' => ['foo'],
            'to' => ['test_too'],
            'tag' => 'test_tag',
            'account_id' => 'test_account_id',
            'application_id' => 'test_application_id',
            'expiration' => 'test_expiration',
            'priority' => 'test_priority',
        ], $bandwidthOptions->toArray());
    }
}
