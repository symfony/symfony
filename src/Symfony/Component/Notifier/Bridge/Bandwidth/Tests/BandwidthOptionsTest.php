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
        $bandwidthOptions = (new BandwidthOptions())
            ->media(['foo'])
            ->tag('test_tag')
            ->accountId('test_account_id')
            ->applicationId('test_application_id')
            ->expiration('test_expiration')
            ->priority('test_priority');

        self::assertSame([
            'media' => ['foo'],
            'tag' => 'test_tag',
            'accountId' => 'test_account_id',
            'applicationId' => 'test_application_id',
            'expiration' => 'test_expiration',
            'priority' => 'test_priority',
        ], $bandwidthOptions->toArray());
    }
}
